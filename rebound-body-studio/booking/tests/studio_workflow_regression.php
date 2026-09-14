<?php
declare(strict_types=1);
namespace Rebound;
foreach (['Clock','Db','Templates','Mailer','Availability','Booking','Admin','Marketing'] as $class) {
    require_once __DIR__ . '/../src/' . $class . '.php';
}
function check(bool $value, string $message): void {
    if (!$value) throw new \RuntimeException($message);
}
$path = tempnam(sys_get_temp_dir(), 'rebound-workflow-');
try {
    $db = Db::sqlite($path);
    foreach (['001_schema.sql','002_campaigns.sql'] as $file) $db->pdo()->exec(file_get_contents(__DIR__.'/../migrations/'.$file));
    $db->setSetting('studio_email','owner@example.test');
    $db->setSetting('site_url','https://example.test/preview/rebound');
    $db->setSetting('min_notice_hours','0');
    $db->run("INSERT INTO services (slug,name,duration_min,price_cents) VALUES ('massage','Therapeutic Massage',60,9500)");
    $db->run("INSERT INTO staff (name,bookable) VALUES ('Randi',1)");
    for($day=0;$day<7;$day++) $db->run('INSERT INTO availability_rules (staff_id,weekday,start_local,end_local) VALUES (1,?,?,?)',[$day,'09:00','18:00']);
    $mail = new Mailer($db, static fn(array $message):bool => true);
    $availability = new Availability($db);
    $booking = new Booking($db,$availability,$mail);
    $newBooking = function(string $email, ?bool $enabled=null) use ($availability,$booking) {
        $days = $availability->slotsForService(1,5);
        $slot = array_values($days)[0][0]['start'];
        return $enabled === null ? $booking->request($email,'Test Client','',1,$slot)
            : $booking->request($email,'Test Client','',1,$slot,'','',$enabled);
    };
    $one = $newBooking('default@example.test');
    check((int)$db->value('SELECT marketing_consent FROM clients WHERE email=?',['default@example.test'])===1,'New customer default enrollment');
    $two = $newBooking('out@example.test',false);
    $out = $db->one('SELECT * FROM clients WHERE email=?',['out@example.test']);
    check((int)$out['marketing_consent']===0 && $out['unsubscribed_at']!==null,'Explicit booking opt-out');
    $newBooking('out@example.test');
    check((int)$db->value('SELECT marketing_consent FROM clients WHERE email=?',['out@example.test'])===0,'Rebooking must preserve opt-out');
    $booking->approve($two['id']);
    check((int)$db->value("SELECT count(*) FROM mail_queue WHERE to_email='out@example.test' AND template='confirmed'")===1,'Opt-out still gets confirmation');
    $marketing = new Marketing($db);
    $key = 'test-campaign-1234567890';
    $queued = $marketing->queue($key,'Studio news','A special offer. {LITERAL}');
    check($queued['queued']===1,'Audience excludes opted-out clients');
    check($marketing->queue($key,'Studio news','A special offer. {LITERAL}')===$queued,'Send retry is idempotent');
    check((int)$db->value('SELECT count(*) FROM campaigns')===1,'Single campaign created');
    $marketingRow=$db->one("SELECT * FROM mail_queue WHERE kind='marketing'");
    $rendered=$mail->render($marketingRow);
    check(str_contains($rendered['text'],'{LITERAL}'),'Keep literal campaign text');
    check(str_contains($rendered['text'],'1138 N. Higley Rd'),'Postal address is present');
    check(str_contains($rendered['headers']['List-Unsubscribe'],'/booking/api/unsubscribe.php?t='),'Unsubscribe endpoint is correct');
    check(str_contains($rendered['text'],'/booking/api/unsubscribe.php?t='),'Body unsubscribe link is correct');
    $default=$db->one('SELECT * FROM clients WHERE email=?',['default@example.test']);
    $mail->unsubscribe($default['unsub_token']);
    $claim=$mail->claimForExternalDelivery(str_repeat('a',32),50);
    check($claim['skipped']===1,'Queued campaign is suppressed after opt-out');
    check(count(array_filter($claim['messages'],fn($m)=>$m['to']==='out@example.test'))>=2,'Transactional messages still deliver after opt-out');
    $newBooking('default@example.test');
    check((int)$db->value('SELECT marketing_consent FROM clients WHERE email=?',['default@example.test'])===0,'Unsubscribe survives next booking');
    $marketing->setPreference((int)$out['id'],true);
    check(count($marketing->audience())===1,'Owner can record customer resubscribe request');
    $second = $marketing->queue('test-campaign-after-claim-12345','Second campaign','Hello');
    $leased = $mail->claimForExternalDelivery(str_repeat('b',32),50);
    $campaignLease = array_values(array_filter($leased['messages'],fn($m)=>$m['subject']==='Second campaign'))[0];
    $marketing->setPreference((int)$out['id'],false);
    check(!$mail->externalDeliveryEligible($campaignLease['queue_id'], str_repeat('b',32)), 'Opt-out after lease prevents SMTP delivery');
    check(count($marketing->audience())===0,'Owner can disable marketing');
    $confirmation=$mail->render($db->one("SELECT * FROM mail_queue WHERE template='confirmed'"));
    check(str_contains($confirmation['text'],'5–10 minutes') && str_contains($confirmation['text'],'50%') && str_contains($confirmation['text'],'100%'),'Confirmation includes supplied arrival and cancellation policy');
    check(!isset($confirmation['headers']['List-Unsubscribe']),'Appointment email has no marketing unsubscribe header');
    $owner=$db->insert('INSERT INTO clients (email,name,created_at,unsub_token) VALUES (?,?,?,?)',['owner@example.test','Owner',Clock::nowSql(),Db::token()]);
    $adminToken=Db::token();
    $db->run('INSERT INTO login_tokens (client_id,token_hash,expires_at) VALUES (?,?,?)',[$owner,hash('sha256',$adminToken),Clock::nowUtc()->modify('+15 minutes')->format(Clock::SQL)]);
    check(Admin::authorized($db,$adminToken),'Studio owner is authorized');
    check(Admin::authorized($db,$adminToken),'Session supports repeated actions');
    $db->run('UPDATE login_tokens SET used_at=?',[Clock::nowSql()]);
    check(!Admin::authorized($db,$adminToken),'Revoked token is denied');
    $clientToken=Db::token();
    $db->run('INSERT INTO login_tokens (client_id,token_hash,expires_at) VALUES (?,?,?)',[$out['id'],hash('sha256',$clientToken),Clock::nowUtc()->modify('+15 minutes')->format(Clock::SQL)]);
    check(!Admin::authorized($db,$clientToken),'Customer token cannot access studio controls');
    echo "studio workflow regression passed: booking opt-out, preserved preferences, transactional mail, campaign deduplication/suppression, policy, owner authorization\n";
} finally { unset($booking,$availability,$mail,$marketing,$db); @unlink($path); }
