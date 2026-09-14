<?php
declare(strict_types=1);
namespace Rebound;
foreach(['Clock','Db','Config','Admin','Mailer','Templates','Packages','SquareClient','SquarePayments'] as $class) require_once __DIR__.'/../../src/'.$class.'.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new \RuntimeException('POST required.');
    $db=Db::sqlite(Config::databasePath()); Admin::requireToken($db,(string)($_POST['admin_token']??''));
    $packages=new Packages($db,new Mailer($db)); $id=(int)($_POST['purchase_id']??0);
    $square=new SquarePayments($db,$packages,new SquareClient(SquareClient::configuration()));
    switch($_POST['action']??'list') {
        case 'list': break;
        case 'pay': $packages->recordPayment($id,(int)($_POST['amount_cents']??0),(string)($_POST['method']??''),(string)($_POST['reference']??'')); break;
        case 'adjust': $packages->adjust($id,(int)($_POST['delta']??0),(string)($_POST['note']??''),(string)($_POST['request_key']??'')); break;
        case 'void': $packages->voidOrder($id); break;
        case 'square_refresh': $square->reconcile($id); break;
        case 'square_close': $square->closeCheckout($id); break;
        default: throw new \RuntimeException('Unknown action.');
    }
    $orders=$packages->adminList();
    foreach($orders as &$order) $order['square']=$db->one('SELECT status,environment,payment_id,refunded_cents,review_reason,checked_at FROM square_checkouts WHERE purchase_id=?',[$order['id']]);
    echo json_encode(['orders'=>$orders,'square'=>$square->status()]);
} catch(\PDOException $e) {error_log($e->getMessage());http_response_code(503);echo json_encode(['error'=>'Unable to save right now. Refresh and try again.']);}
catch(\Throwable $e) {http_response_code(400);echo json_encode(['error'=>$e->getMessage()]);}
