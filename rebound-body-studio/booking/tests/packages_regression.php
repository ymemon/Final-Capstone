<?php
declare(strict_types=1);
namespace Rebound;
foreach(['Clock','Db','Templates','Mailer','Availability','Booking','Packages','Admin'] as $c) require_once __DIR__.'/../src/'.$c.'.php';
function ok(bool $test,string $message):void {if(!$test)throw new \RuntimeException($message);}
function rejects(callable $fn,string $label):void {$failed=false;try{$fn();}catch(\RuntimeException $e){$failed=true;}ok($failed,$label);}
$path=tempnam(sys_get_temp_dir(),'rebound-packages-');
try {
    $db=Db::sqlite($path);
    foreach(['001_schema.sql','002_campaigns.sql','003_packages.sql','004_current_catalog.sql'] as $f) $db->pdo()->exec(file_get_contents(__DIR__.'/../migrations/'.$f));
    $db->setSetting('studio_email','owner@example.test');$db->setSetting('site_url','https://example.test/studio');$db->setSetting('min_notice_hours','0');
    $db->run("INSERT INTO staff(name,bookable) VALUES('Randi',1)");
    for($day=0;$day<7;$day++)$db->run('INSERT INTO availability_rules(staff_id,weekday,start_local,end_local) VALUES(1,?,?,?)',[$day,'08:00','19:00']);
    $mail=new Mailer($db,static fn(array $m):bool=>true);$packages=new Packages($db,$mail);$avail=new Availability($db);$book=new Booking($db,$avail,$mail);
    $catalog=$packages->catalog();ok(count($catalog)===2,'Exactly two real three-packs');
    ok((int)$db->value('SELECT count(*) FROM services WHERE active=1')===17,'Current Randi service variants');
    ok((int)$db->value("SELECT count(*) FROM services WHERE active=1 AND slug LIKE 'cupping%'")===0,'Uncertified service is not bookable');
    $token=bin2hex(random_bytes(32));$p=$packages->order((int)$catalog[0]['id'],'buyer@example.test','Buyer','555',true,$token);$id=(int)$p['id'];
    ok($p['status']==='pending_payment' && $p['price_paid_cents']===0,'No uncollected payment claimed');
    ok($packages->order((int)$catalog[0]['id'],'buyer@example.test','Buyer','555',true,$token)['id']===$id,'Order retry returns same order');
    ok((int)$db->value('SELECT count(*) FROM package_purchases')===1,'No duplicate purchase');
    ok((int)$db->value('SELECT count(*) FROM mail_queue')===2,'One customer order and studio alert');
    $service=(int)$p['services'][0]['id'];
    $request=function(?string $key=null,?int $sid=null,?int $pid=null)use($avail,$book,$service,$id,$token){
        $sid??=$service;$slot=array_values($avail->slotsForService($sid,10))[0][0]['start'];
        return $book->request('gift@example.test','Gift recipient','',$sid,$slot,'','',false,$pid??$id,$key??$token);
    };
    rejects(fn()=>$request(),'Unpaid credit denied');
    rejects(fn()=>$packages->recordPayment($id,1,'Square','receipt-1'),'Incorrect payment denied');
    $packages->recordPayment($id,26500,'Square','receipt-1');$packages->recordPayment($id,26500,'Square','receipt-1');
    ok((int)$db->value("SELECT count(*) FROM mail_queue WHERE dedupe_key='package-paid:1'")===1,'Payment retry emits one receipt');
    rejects(fn()=>$request(''),'Missing proof denied');
    rejects(fn()=>$request(str_repeat('f',64)),'Forged proof denied');
    $wrong=(int)$db->value("SELECT id FROM services WHERE slug='therapeutic-massage_90'");rejects(fn()=>$request(null,$wrong),'Wrong duration denied');
    $stretch=(int)$db->value("SELECT id FROM services WHERE slug='stretch-session_60'");rejects(fn()=>$request(null,$stretch),'Ineligible service denied');
    $a=$request();$balance=$packages->summary($token);ok($balance['remaining']===2 && $balance['reserved']===1,'Gift request reserves exactly one');
    ok(!isset($balance['client_email']) && !isset($balance['payment_reference']),'Gift link hides buyer contact and payment reference');
    $book->decline($a['id']);ok($packages->summary($token)['remaining']===3,'Decline returns credit');
    rejects(fn()=>$book->decline($a['id']),'Double decline rejected');ok($packages->summary($token)['remaining']===3,'No extra credit');
    $a=$request();$book->approve($a['id']);
    $confirmation=$mail->render($db->one("SELECT * FROM mail_queue WHERE template='confirmed' AND appointment_id=?",[$a['id']]));
    ok(str_contains($confirmation['text'],'prepaid package session') && str_contains($confirmation['text'],'Google Maps'),'Prepaid confirmation and directions');
    rejects(fn()=>$packages->adjust($id,1,'Restore reserved',bin2hex(random_bytes(32))),'Reserved credit cannot be manually restored');
    $book->cancel($a['id']);ok($packages->summary($token)['remaining']===3,'Cancellation returns credit');
    $a=$request();$book->approve($a['id']);$book->markCompleted($a['id']);
    $b=$packages->summary($token);ok($b['remaining']===2 && $b['reserved']===0 && $b['completed_or_used']===1,'Completed usage tracked');
    $key=bin2hex(random_bytes(32));$packages->adjust($id,1,'Goodwill restoration',$key);$packages->adjust($id,1,'Goodwill restoration',$key);
    ok($packages->summary($token)['remaining']===3,'Adjustment retry cannot mint credits');
    rejects(fn()=>$packages->adjust($id,1,'Too many credits',bin2hex(random_bytes(32))),'Balance capped at purchased sessions');
    for($i=0;$i<3;$i++){$a=$request();$book->approve($a['id']);$book->markNoShow($a['id']);}
    ok($packages->summary($token)['remaining']===0,'No-show retains session usage');rejects(fn()=>$request(),'Exhausted package denied');
    ok((int)$db->value('SELECT sessions_used FROM package_purchases WHERE id=?',[$id])===-(int)$db->value('SELECT SUM(delta) FROM credit_ledger WHERE package_purchase_id=?',[$id]),'Ledger agrees with counter');
    $other=bin2hex(random_bytes(32));$second=$packages->order((int)$catalog[1]['id'],'buyer@example.test','Wrong overwrite','',false,$other);
    ok((int)$db->value("SELECT marketing_consent FROM clients WHERE email='buyer@example.test'")===0,'Package order preserves opt-out');
    ok($db->value("SELECT name FROM clients WHERE email='buyer@example.test'")==='Buyer','Order preserves existing identity');
    rejects(fn()=>$packages->recordPayment((int)$second['id'],36000,'Square','receipt-1'),'Receipt reuse denied');
    rejects(fn()=>$request($other,null,$id),'Cross-package proof denied');
    $packages->recordPayment((int)$second['id'],36000,'Square','receipt-2');
    $db->run('UPDATE package_purchases SET expires_at=? WHERE id=?',[Clock::nowUtc()->modify('-1 day')->format(Clock::SQL),$second['id']]);
    rejects(fn()=>$request($other,$wrong,(int)$second['id']),'Expired package denied');
    ok($packages->summary($other)['status']==='expired','Expired status visible');
    $packages->recover('buyer@example.test');$recovery=$mail->render($db->one("SELECT * FROM mail_queue WHERE subject='Your package balance links'"));
    preg_match_all('/#token=([a-f0-9]{64})/',$recovery['text'],$links);ok(count($links[1])===2,'Recovery includes both orders');
    ok($packages->summary($links[1][0])['id']===$second['id'],'Recovery token authorizes correct order');
    ok(!Admin::authorized($db,$token),'Package token cannot authorize owner even for same email');
    $before=(int)$db->value('SELECT count(*) FROM mail_queue');$packages->recover('unknown@example.test');ok((int)$db->value('SELECT count(*) FROM mail_queue')===$before,'Unknown recovery creates no email');
    $thirdToken=bin2hex(random_bytes(32));$third=$packages->order((int)$catalog[0]['id'],'third@example.test','Third','',false,$thirdToken);$packages->voidOrder((int)$third['id']);
    rejects(fn()=>$packages->recordPayment((int)$third['id'],26500,'Cash','cash-receipt-3'),'Cancelled unpaid order cannot activate');
    for($i=0;$i<2;$i++)$packages->limit('test-limit',2);rejects(fn()=>$packages->limit('test-limit',2),'Recovery/order throttling');
    echo "Packages regression passed: independent order, payment validation, retries, gift redemption, authorization, eligibility, refunds, no-show, balance ledger, adjustments, expiry, recovery, opt-out and rate limiting.\n";
} finally {unset($book,$avail,$mail,$packages,$db);@unlink($path);}
