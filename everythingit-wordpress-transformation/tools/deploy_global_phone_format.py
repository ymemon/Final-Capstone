"""Correct the EverythingIT global header phone display format."""

import ast
import json
import os
import re
import time
from pathlib import Path

import paramiko
import requests


HOST="198185.eu13.ssh.myftpupload.com";USER="client_c47ef96dfe_198185";ROOT="/home/client_c47ef96dfe_198185/html"
TEMPLATE_ID=247;WIDGET_ID="af8e0b4";OLD="+3531 524 0755";NEW="+353 1 524 0755"
CREDENTIAL_SOURCE=Path(os.environ.get("EIT_CREDENTIAL_SOURCE",r"C:\Users\yasir\Downloads\Other\eit_case_studies_rebuild.py"))


def load_password()->str:
    source=CREDENTIAL_SOURCE.read_text(encoding="utf-8-sig");match=re.search(r"^PASSWORD\s*=\s*(.+)$",source,re.MULTILINE)
    if not match:raise RuntimeError("Password assignment not found")
    password=ast.literal_eval(match.group(1).strip())
    if not isinstance(password,str) or not password:raise RuntimeError("Password is empty")
    return password


PHP=f'''<?php
require_once __DIR__ . '/wp-load.php';
$id={TEMPLATE_ID};$widgetId='{WIDGET_ID}';$old='{OLD}';$new='{NEW}';
$raw=get_post_meta($id,'_elementor_data',true);$data=json_decode($raw,true);
if(!is_array($data)){{throw new RuntimeException('Template Elementor JSON invalid');}}
$matches=0;$before='';
$edit=function(&$nodes)use(&$edit,$widgetId,$old,$new,&$matches,&$before){{foreach($nodes as &$node){{
 if(($node['id']??'')===$widgetId){{$before=$node['settings']['text']??'';if($before!==$old&&$before!==$new){{throw new RuntimeException('Unexpected phone text: '.$before);}}$node['settings']['text']=$new;$node['settings']['link']['url']='tel:+35315240755';$matches++;}}
 if(!empty($node['elements'])){{$edit($node['elements']);}}
}}}};$edit($data);
if($matches!==1){{throw new RuntimeException('Expected one phone widget; found '.$matches);}}
$stamp=gmdate('Ymd-His');$backupDir='/home/client_c47ef96dfe_198185/eit-backups';if(!is_dir($backupDir)&&!mkdir($backupDir,0750,true)){{throw new RuntimeException('Backup directory unavailable');}}
$backup=$backupDir.'/header-phone-before-format-'.$stamp.'.json';if(file_put_contents($backup,wp_json_encode(['elementor_data'=>$raw],JSON_UNESCAPED_SLASHES))===false){{throw new RuntimeException('Backup write failed');}}
add_post_meta($id,'_eit_header_phone_before_format_'.$stamp,$raw,true);update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
delete_post_meta($id,'_elementor_element_cache');delete_post_meta($id,'_elementor_css');clean_post_cache($id);wp_cache_flush();if(class_exists('Elementor\\Plugin')){{Elementor\\Plugin::$instance->files_manager->clear_cache();}}
if(isset($GLOBALS['wpaas_cache_class'])){{if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{$GLOBALS['wpaas_cache_class']->do_ban();}}if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$GLOBALS['wpaas_cache_class']->flush_cdn();}}}}
echo wp_json_encode(['template_id'=>$id,'matches'=>$matches,'before'=>$before,'after'=>$new,'backup'=>basename($backup)]);
'''


def main()->None:
    stamp=int(time.time());local=Path(__file__).resolve().parent/f".eit-phone-format-{stamp}.php";remote=local.name;local.write_text(PHP,encoding="utf-8",newline="\n")
    ssh=paramiko.SSHClient();ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy());sftp=None
    try:
        ssh.connect(HOST,username=USER,password=load_password(),timeout=30);sftp=ssh.open_sftp();sftp.put(str(local),f"{ROOT}/{remote}");_,stdout,stderr=ssh.exec_command(f"cd {ROOT} && php -l {remote} && wp eval-file {remote}",timeout=240)
        output=stdout.read().decode("utf-8","replace");error=stderr.read().decode("utf-8","replace");print(output,end="");print(error,end="")
        if stdout.channel.recv_exit_status()!=0:raise RuntimeError("Remote deployment failed")
    finally:
        if sftp is not None:
            try:sftp.remove(f"{ROOT}/{remote}")
            except OSError:pass
            sftp.close()
        ssh.close();local.unlink(missing_ok=True)
    response=requests.get(f"https://everythingit.ie/?phone-format={stamp}",timeout=60);response.raise_for_status();html=response.text
    checks={"old_absent":OLD not in html,"new_present":NEW in html,"tel_link":'href="tel:+35315240755"' in html}
    print(json.dumps({"status":response.status_code,"public":checks}))
    if not all(checks.values()):raise RuntimeError(f"Public verification failed: {checks}")


if __name__=="__main__":main()
