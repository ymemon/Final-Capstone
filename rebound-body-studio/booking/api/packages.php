<?php
declare(strict_types=1);
namespace Rebound;
foreach(['Clock','Db','Config','Mailer','Templates','Packages','SquareClient','SquarePayments'] as $class) require_once __DIR__.'/../src/'.$class.'.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $db=Db::sqlite(Config::databasePath()); $client=new SquareClient(SquareClient::configuration());
    $packages=new Packages($db,new Mailer($db),$client->enabled()); $square=new SquarePayments($db,$packages,$client);
    if($_SERVER['REQUEST_METHOD']==='GET') {echo json_encode(['packages'=>$packages->catalog(),'online_payment'=>$client->enabled(),'sandbox'=>$client->value('environment')==='sandbox']);exit;}
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new \RuntimeException('POST required.');
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(strlen($raw)>16384) throw new \RuntimeException('Request is too large.');
    $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($p)) throw new \RuntimeException('Invalid request.');
    $action=(string)($p['action']??'');
    if(in_array($action,['order','recover'],true)) $packages->limit($action.':'.($_SERVER['REMOTE_ADDR']??''),12);
    if($action==='order') {
        echo json_encode($packages->order((int)($p['packageId']??0),(string)($p['email']??''),(string)($p['name']??''),
            (string)($p['phone']??''),filter_var($p['marketingOptOut']??false,FILTER_VALIDATE_BOOLEAN),(string)($p['requestKey']??'')));
    } elseif($action==='view') echo json_encode($packages->summary((string)($p['token']??'')));
    elseif(in_array($action,['checkout','refresh_payment'],true)) {
        $token=(string)($p['token']??''); $id=$packages->authorizedPurchase($token);
        $packages->limit('square:'.$action.':'.$id,$action==='checkout'?12:90);
        echo json_encode($action==='checkout'?$square->checkout($token):$square->refresh($token));
    }
    elseif($action==='recover') {
        $packages->recover((string)($p['email']??''));
        echo json_encode(['message'=>'If there are packages for that email, a private link will arrive shortly.']);
    } else throw new \RuntimeException('Unknown action.');
} catch(\JsonException $e) {http_response_code(400);echo json_encode(['error'=>'Invalid JSON request.']);}
catch(\PDOException $e) {
    error_log('Package database error: '.$e->getMessage()); http_response_code(503);
    echo json_encode(['error'=>'Unable to save right now. Please try again.']);
} catch(\Throwable $e) {http_response_code(400);echo json_encode(['error'=>$e->getMessage()]);}
