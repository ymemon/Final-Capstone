<?php
declare(strict_types=1);
namespace Rebound;
foreach(['Clock','Db','Templates','Mailer','Packages','SquareClient','SquarePayments'] as $c) require_once __DIR__.'/../src/'.$c.'.php';
function check(bool $value,string $label):void {if(!$value)throw new \RuntimeException($label);}
function reject(callable $fn,string $label):void {try{$fn();}catch(\RuntimeException|\InvalidArgumentException $e){return;}throw new \RuntimeException($label);}
$path=tempnam(sys_get_temp_dir(),'rebound-square-');
$config=['environment'=>'production','enabled'=>true,'access_token'=>'fake-private-token','location_id'=>'L1','merchant_id'=>'M1',
    'webhook_signature_key'=>'test-signature-key','webhook_url'=>'https://studio.example.test/booking/api/square-webhook.php'];
$remote=['links'=>[],'orders'=>[],'payments'=>[],'refunds'=>[],'calls'=>[],'timeout'=>false,'apiFailure'=>false];
$transport=function(string $method,string $path,?array $body)use(&$remote):array {
    $remote['calls'][]=[$method,$path,$body];
    if($remote['apiFailure'])throw new \RuntimeException('Simulated API failure.');
    if($method==='POST'){
        $key=$body['idempotency_key'];
        if(!isset($remote['links'][$key])){
            $n=count($remote['links'])+1;$remote['links'][$key]=['id'=>'LINK'.$n,'order_id'=>'ORDER'.$n,'url'=>'https://square.link/u/link'.$n];
            $remote['orders']['ORDER'.$n]=['id'=>'ORDER'.$n,'location_id'=>'L1','state'=>'DRAFT','tenders'=>[]];
        }
        if($remote['timeout']){$remote['timeout']=false;throw new \RuntimeException('Timeout after Square created checkout.');}
        return ['payment_link'=>$remote['links'][$key]];
    }
    if($method==='DELETE'){
        foreach($remote['links'] as $link)if($path==='/v2/online-checkout/payment-links/'.$link['id']){
            $remote['orders'][$link['order_id']]['state']='CANCELED';return ['id'=>$link['id'],'cancelled_order_id'=>$link['order_id']];
        }
    }
    if(str_starts_with($path,'/v2/payments/'))return ['payment'=>$remote['payments'][basename($path)]??[]];
    if(str_starts_with($path,'/v2/orders/'))return ['order'=>$remote['orders'][basename($path)]??[]];
    if(str_starts_with($path,'/v2/refunds/'))return ['refund'=>$remote['refunds'][basename($path)]??[]];
    throw new \RuntimeException('Unexpected mock call '.$path);
};
try{
    $db=Db::sqlite($path);
    foreach(glob(__DIR__.'/../migrations/*.sql') as $migration)$db->pdo()->exec(file_get_contents($migration));
    $db->setSetting('site_url','https://studio.example.test');$db->setSetting('studio_email','owner@example.test');
    $packages=new Packages($db,new Mailer($db,static fn(array $m):bool=>true),true);
    $client=new SquareClient($config,$transport);$square=new SquarePayments($db,$packages,$client);$catalog=$packages->catalog();
    $order=function(int $index=0)use($packages,$catalog):array{$t=bin2hex(random_bytes(32));return [$t,$packages->order((int)$catalog[$index]['id'],'buyer@example.test','Buyer','',false,$t)];};
    $sign=fn(string $body):string=>base64_encode(hash_hmac('sha256',$config['webhook_url'].$body,$config['webhook_signature_key'],true));
    $event=function(string $id,string $payment='PAY1',string $type='payment.updated')use($square,$sign):void{
        $body=json_encode(['event_id'=>$id,'merchant_id'=>'M1','type'=>$type,'data'=>['object'=>['payment'=>['id'=>$payment]]]],JSON_THROW_ON_ERROR);
        $square->webhook($body,$sign($body));
    };
    check(!(new SquareClient([]))->enabled(),'Missing credentials fail closed');
    check(!(new SquareClient(array_replace($config,['environment'=>'sandbox'])))->enabled(),'Sandbox is never exposed on the production UI by default');
    [$token,$p]=$order();$id=(int)$p['id'];
    reject(fn()=>$square->checkout(str_repeat('f',64)),'Forged package token must fail');
    $remote['timeout']=true;reject(fn()=>$square->checkout($token),'Creation timeout must not activate');
    check($packages->summary($token)['status']==='pending_payment','Timeout leaves payment pending');
    reject(fn()=>$packages->recordPayment($id,26500,'Cash','offline-1'),'Creation in flight blocks offline activation');
    reject(fn()=>$packages->voidOrder($id),'Creation in flight blocks cancellation');
    $link=$square->checkout($token);$again=$square->checkout($token);
    check($link===$again && count($remote['links'])===1,'Checkout retry reuses one Square order');
    $post=array_values(array_filter($remote['calls'],fn($c)=>$c[0]==='POST'));
    check($post[0][2]===$post[1][2],'Timeout retry payload is identical');
    check($post[0][2]['order']['line_items'][0]['base_price_money']['amount']===26500,'Server owns exact USD price');
    check(!str_contains(json_encode($post),$token) && !str_contains(json_encode($post),'buyer@example.test'),'Square payload excludes private package key and buyer identity');
    check($square->refresh($token)['status']==='pending_payment','Redirect/refresh alone grants no sessions');
    $payment=['id'=>'PAY1','order_id'=>'ORDER1','location_id'=>'L1','status'=>'APPROVED','amount_money'=>['amount'=>26500,'currency'=>'USD'],'total_money'=>['amount'=>26500,'currency'=>'USD']];
    $remote['payments']['PAY1']=$payment;
    $body=json_encode(['event_id'=>'bad','merchant_id'=>'M1','type'=>'payment.updated','data'=>['object'=>['payment'=>['id'=>'PAY1']]]]);
    reject(fn()=>$square->webhook($body,'bad'),'Invalid signature is rejected');
    reject(fn()=>$square->webhook($body.' ',$sign($body)),'Signature uses exact raw body');
    $other=str_replace('M1','M2',$body);reject(fn()=>$square->webhook($other,$sign($other)),'Wrong merchant is rejected');
    $event('approved');check($packages->summary($token)['status']==='pending_payment','Authorization is not capture');
    $remote['payments']['PAY1']['status']='FAILED';$event('failed');check($packages->summary($token)['status']==='pending_payment','Decline grants no credits');
    $remote['payments']['PAY1']['status']='COMPLETED';$remote['orders']['ORDER1']['tenders']=[['payment_id'=>'PAY1']];
    $remote['orders']['ORDER1']['state']='COMPLETED';
    $remote['apiFailure']=true;reject(fn()=>$event('retry'),'API outage must be retryable');$remote['apiFailure']=false;
    check(!$db->value("SELECT 1 FROM square_events WHERE event_id='retry'"),'Failed event is not acknowledged in database');
    $event('retry');$event('retry');$event('different-event-same-payment');$square->refresh($token);
    check($packages->summary($token)['status']==='active' && $packages->summary($token)['remaining']===3,'Captured payment grants exactly three sessions');
    check((int)$db->value("SELECT count(*) FROM mail_queue WHERE dedupe_key='package-paid:1'")===1,'Only one activation email is queued');
    check(!isset($packages->summary($token)['payment_reference']),'Gift view hides payment ID');
    reject(fn()=>$square->checkout($token),'Paid order never receives a new payable link');
    $remote['payments']['PAY1']['refunded_money']=['amount'=>1000,'currency'=>'USD'];
    $remote['refunds']['REF1']=['id'=>'REF1','payment_id'=>'PAY1','status'=>'COMPLETED'];
    $body=json_encode(['event_id'=>'refund','merchant_id'=>'M1','type'=>'refund.updated','data'=>['object'=>['refund'=>['id'=>'REF1']]]]);
    $square->webhook($body,$sign($body));check($packages->summary($token)['status']==='payment_review','Partial refund freezes redemption for review');
    unset($remote['payments']['PAY1']['refunded_money']);$event('stale');check($packages->summary($token)['status']==='payment_review','Stale completed event cannot reactivate refunded credit');
    $remote['payments']['PAY1']['refunded_money']=['amount'=>26500,'currency'=>'USD'];$event('full-refund');
    check($packages->summary($token)['status']==='refunded','Full refund blocks redemption');
    [$t2,$p2]=$order(1);$square->checkout($t2);
    $remote['payments']['PAY2']=['id'=>'PAY2','order_id'=>'ORDER2','location_id'=>'L1','status'=>'COMPLETED','amount_money'=>['amount'=>1,'currency'=>'USD'],'total_money'=>['amount'=>1,'currency'=>'USD']];
    $event('mismatch','PAY2');check($packages->summary($t2)['status']==='payment_review','Amount mismatch never grants sessions');
    [$t3,$p3]=$order();$square->checkout($t3);
    $square->closeCheckout((int)$p3['id']);$packages->recordPayment((int)$p3['id'],26500,'Cash','cash-after-square-close');
    check($packages->summary($t3)['status']==='active','Offline payment is allowed only after verified Square cancellation');
    [$t4,$p4]=$order();$square->checkout($t4);
    $remote['payments']['PAY4']=['id'=>'PAY4','order_id'=>'ORDER4','location_id'=>'L2','status'=>'COMPLETED','amount_money'=>['amount'=>26500,'currency'=>'USD'],'total_money'=>['amount'=>26500,'currency'=>'USD']];
    reject(fn()=>$event('wrong-location','PAY4'),'Wrong location fails');check($packages->summary($t4)['status']==='pending_payment','Wrong location grants no credits');
    $otherConfig=array_replace($config,['merchant_id'=>'OTHER']);$otherSquare=new SquarePayments($db,$packages,new SquareClient($otherConfig,$transport));
    reject(fn()=>$otherSquare->checkout($t4),'Changing merchant cannot charge an old order');
    $remote['payments']['POS1']=['id'=>'POS1','order_id'=>'POSORDER','location_id'=>'L1','status'=>'COMPLETED'];$event('pos','POS1');
    check((int)$db->value('SELECT count(*) FROM package_purchases')===4,'Unrelated Square Stand payment creates no package');
    echo "Square regression passed: idempotent checkout, uncertain timeout recovery, exact price, private keys, signature, account isolation, capture-only activation, retry, duplicate events, refunds, stale events, offline conflict and unrelated POS payments.\n";
}finally{unset($square,$packages,$db);@unlink($path);}
