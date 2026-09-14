<?php
declare(strict_types=1);
namespace Rebound;
foreach (['Clock','Db','Admin'] as $class) require_once __DIR__.'/../src/'.$class.'.php';
$db = Db::sqlite(':memory:');
$db->pdo()->exec(file_get_contents(__DIR__.'/../migrations/001_schema.sql'));
$db->setSetting('studio_email', 'studio@example.test');
foreach (['studio@example.test'=>'studio-token','tester@example.test'=>'tester-token'] as $email=>$token) {
    $id=$db->insert('INSERT INTO clients (email,name,created_at,unsub_token) VALUES (?,?,?,?)',[$email,'Test',Clock::nowSql(),Db::token()]);
    $db->run('INSERT INTO login_tokens (client_id,token_hash,expires_at) VALUES (?,?,?)',[$id,hash('sha256',$token),Clock::nowUtc()->modify('+15 minutes')->format(Clock::SQL)]);
}
function check(bool $ok):void { if(!$ok) throw new \RuntimeException('Login override check failed'); }
check(Admin::authorized($db,'studio-token') && !Admin::authorized($db,'tester-token'));
$db->setSetting('admin_login_email',' Tester@Example.Test ');
check(Admin::loginEmail($db)==='tester@example.test');
check(Admin::authorized($db,'tester-token') && !Admin::authorized($db,'studio-token'));
check($db->setting('studio_email')==='studio@example.test');
$db->setSetting('admin_login_email','');
check(Admin::authorized($db,'studio-token') && !Admin::authorized($db,'tester-token'));
echo "Admin login override regression passed.\n";
