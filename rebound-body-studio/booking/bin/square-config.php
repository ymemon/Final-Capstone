<?php
declare(strict_types=1);
namespace Rebound;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
foreach(['Clock','Db','Config','SquareClient'] as $c)require_once __DIR__.'/../src/'.$c.'.php';

// Credential input is JSON on stdin, never CLI arguments or printed output.
// discover: {"environment":"production","access_token":"..."}
// install: environment, access_token, merchant_id, location_id, webhook_signature_key, webhook_url.
// check / enable / disable use the installed private configuration.
$mode=$argv[1]??'check';
try{
    if(in_array($mode,['discover','install'],true)){
        $input=stream_get_contents(STDIN,16385);
        if(strlen($input)>16384)throw new \RuntimeException('Input too large.');
        $config=json_decode($input,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($config))throw new \RuntimeException('Provide a JSON object on stdin.');
    }else $config=SquareClient::configuration();
    $client=new SquareClient($config);
    if($mode==='discover'){
        $merchant=$client->request('GET','/v2/merchants/me')['merchant']??[];
        $locations=$client->request('GET','/v2/locations')['locations']??[];
        echo json_encode(['merchant_id'=>$merchant['id']??null,'business_name'=>$merchant['business_name']??null,
            'locations'=>array_map(fn($l)=>array_intersect_key($l,array_flip(['id','name','status','currency','capabilities','merchant_id'])),$locations)],JSON_PRETTY_PRINT)."\n";exit;
    }
    if(!in_array($mode,['install','check','enable','disable'],true))throw new \RuntimeException('Use discover, install, check, enable or disable.');
    $db=Db::sqlite(Config::databasePath());
    if($mode==='install'){
        $config=array_intersect_key($config,array_flip(['environment','access_token','location_id','merchant_id','webhook_signature_key','webhook_url']));
        $config['enabled']=false;$client=new SquareClient($config);
        if(!$client->configured())throw new \RuntimeException('Missing or invalid Square configuration.');
        $expected=rtrim($db->setting('site_url',''),'/').'/booking/api/square-webhook.php';
        if($client->value('webhook_url')!==$expected)throw new \RuntimeException('The webhook URL must exactly match '.$expected);
    }
    $identity=$mode==='disable'?[]:$client->validateAccount();
    if($mode==='enable'){
        if(!$client->configured())throw new \RuntimeException('Install the connection first.');
        if($db->setting('square_webhook_verified','')!==$client->fingerprint())throw new \RuntimeException('Send a signed Square webhook test to this endpoint before enabling checkout.');
        if($client->value('environment')==='sandbox'&&getenv('REBOUND_SQUARE_ALLOW_SANDBOX')!=='1')throw new \RuntimeException('Sandbox checkout is permitted only on an explicitly isolated test server.');
        $config['enabled']=true;
    }
    if($mode==='disable')$config['enabled']=false;
    if($mode!=='check'){
        $path=SquareClient::configurationPath();
        if(!is_dir(dirname($path))&&!mkdir(dirname($path),0700,true))throw new \RuntimeException('Could not create the private configuration directory.');
        $existing=is_file($path)?require($path):[];
        if(!is_array($existing))throw new \RuntimeException('Existing config.php is not an array; set a separate REBOUND_SQUARE_CONFIG path.');
        $old=$existing['square']??[];
        if($mode==='install'&&$old){
            foreach(['environment','merchant_id','location_id'] as $key)if(($old[$key]??'')!==($config[$key]??''))throw new \RuntimeException('Changing the connected account requires a separate reviewed migration.');
        }
        $existing['square']=$config;
        $temporary=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $oldMask=umask(0077);
        try{
            if(file_put_contents($temporary,"<?php\nreturn ".var_export($existing,true).";\n",LOCK_EX)===false)throw new \RuntimeException('Could not write private configuration.');
            chmod($temporary,0600);
            if(!rename($temporary,$path))throw new \RuntimeException('Could not publish private configuration.');
            if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
        }finally{umask($oldMask);if(is_file($temporary))unlink($temporary);}
    }
    echo json_encode(['account'=>$identity,'online_checkout_enabled'=>($config['enabled']??false)===true,
        'webhook_verified'=>$db->setting('square_webhook_verified','')===$client->fingerprint()],JSON_PRETTY_PRINT)."\n";
}catch(\Throwable $e){fwrite(STDERR,'Square setup failed: '.$e->getMessage()."\n");exit(1);}
