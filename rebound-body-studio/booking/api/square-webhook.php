<?php
declare(strict_types=1);
namespace Rebound;
foreach(['Clock','Db','Config','Mailer','Templates','Packages','SquareClient','SquarePayments'] as $class) require_once __DIR__.'/../src/'.$class.'.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); echo '{"error":"POST required"}'; exit; }
$raw=file_get_contents('php://input',false,null,0,1048577);
if (strlen($raw)>1048576) {http_response_code(413);echo '{"error":"Request too large"}';exit;}
try {
    $client=new SquareClient(SquareClient::configuration());
    if (!$client->configured()) {http_response_code(503);echo '{"error":"Square connection pending"}';exit;}
    $db=Db::sqlite(Config::databasePath());
    $square=new SquarePayments($db,new Packages($db,new Mailer($db)),$client);
    $square->webhook($raw,(string)($_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? ''));
    echo '{"received":true}';
} catch (\InvalidArgumentException $e) {http_response_code(403);echo '{"error":"Invalid event"}';}
catch (\JsonException $e) {http_response_code(400);echo '{"error":"Invalid JSON"}';}
catch (\Throwable $e) {error_log('Rebound Square webhook processing failed; retry required.');http_response_code(503);echo '{"error":"Please retry"}';}
