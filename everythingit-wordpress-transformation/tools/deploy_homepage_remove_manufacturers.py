"""Remove both legacy manufacturer-logo sections from the EverythingIT homepage."""

import ast
import json
import os
import re
import time
from pathlib import Path

import paramiko
import requests


HOST = "198185.eu13.ssh.myftpupload.com"
USER = "client_c47ef96dfe_198185"
ROOT = "/home/client_c47ef96dfe_198185/html"
PAGE_ID = 146
TARGET_IDS = ("2c9fbc0", "6e14dab")
CREDENTIAL_SOURCE = Path(os.environ.get(
    "EIT_CREDENTIAL_SOURCE",
    r"C:\Users\yasir\Downloads\Other\eit_case_studies_rebuild.py",
))


def load_password() -> str:
    source = CREDENTIAL_SOURCE.read_text(encoding="utf-8-sig")
    match = re.search(r"^PASSWORD\s*=\s*(.+)$", source, re.MULTILINE)
    if not match:
        raise RuntimeError("Password assignment not found in external credential source")
    password = ast.literal_eval(match.group(1).strip())
    if not isinstance(password, str) or not password:
        raise RuntimeError("External password value is empty")
    return password


PHP = f'''<?php
require_once __DIR__ . '/wp-load.php';
$id={PAGE_ID};$targets=json_decode('{json.dumps(TARGET_IDS)}',true);
$raw=get_post_meta($id,'_elementor_data',true);$data=json_decode($raw,true);
if(!is_array($data)){{throw new RuntimeException('Homepage Elementor JSON is invalid');}}
$removed=[];
$filter=function(&$nodes)use(&$filter,$targets,&$removed){{
  $kept=[];
  foreach($nodes as $node){{
    $nodeId=$node['id']??'';
    if(in_array($nodeId,$targets,true)){{$removed[]=$nodeId;continue;}}
    if(!empty($node['elements'])){{$filter($node['elements']);}}
    $kept[]=$node;
  }}
  $nodes=$kept;
}};
$filter($data);sort($removed);$expected=$targets;sort($expected);
if($removed!==$expected){{throw new RuntimeException('Expected both manufacturer sections; removed '.implode(',',$removed));}}
$stamp=gmdate('Ymd-His');$backupDir='/home/client_c47ef96dfe_198185/eit-backups';
if(!is_dir($backupDir)&&!mkdir($backupDir,0750,true)){{throw new RuntimeException('Backup directory unavailable');}}
$backup=$backupDir.'/homepage-before-manufacturers-removal-'.$stamp.'.json';
if(file_put_contents($backup,wp_json_encode(['elementor_data'=>$raw],JSON_UNESCAPED_SLASHES))===false){{throw new RuntimeException('Backup write failed');}}
add_post_meta($id,'_eit_home_before_manufacturers_removal_'.$stamp,$raw,true);
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
delete_post_meta($id,'_elementor_element_cache');delete_post_meta($id,'_elementor_css');clean_post_cache($id);wp_cache_flush();
if(class_exists('Elementor\\Plugin')){{Elementor\\Plugin::$instance->files_manager->clear_cache();}}
if(isset($GLOBALS['wpaas_cache_class'])){{
 if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{$GLOBALS['wpaas_cache_class']->do_ban();}}
 if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$GLOBALS['wpaas_cache_class']->flush_cdn();}}
}}
$render=Elementor\\Plugin::$instance->frontend->get_builder_content_for_display($id);
foreach($targets as $target){{if(strpos($render,'data-id="'.$target.'"')!==false){{throw new RuntimeException('Removed section still rendered: '.$target);}}}}
echo wp_json_encode(['page_id'=>$id,'removed'=>$removed,'backup'=>basename($backup),'reviews'=>substr_count($render,'class="eit-google-reviews"'),'procurement'=>strpos($render,'data-id="c58e3f3"')!==false]);
'''


def main() -> None:
    stamp=int(time.time());local_payload=Path(__file__).resolve().parent/f".eit-remove-manufacturers-{stamp}.php";remote_name=local_payload.name
    local_payload.write_text(PHP,encoding="utf-8",newline="\n")
    ssh=paramiko.SSHClient();ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy());sftp=None
    try:
        ssh.connect(HOST,username=USER,password=load_password(),timeout=30);sftp=ssh.open_sftp();sftp.put(str(local_payload),f"{ROOT}/{remote_name}")
        _,stdout,stderr=ssh.exec_command(f"cd {ROOT} && php -l {remote_name} && wp eval-file {remote_name}",timeout=240)
        output=stdout.read().decode("utf-8","replace");error=stderr.read().decode("utf-8","replace");print(output,end="");print(error,end="")
        if stdout.channel.recv_exit_status()!=0:raise RuntimeError("Remote deployment failed")
    finally:
        if sftp is not None:
            try:sftp.remove(f"{ROOT}/{remote_name}")
            except OSError:pass
            sftp.close()
        ssh.close();local_payload.unlink(missing_ok=True)
    response=requests.get(f"https://everythingit.ie/?manufacturer-removal={stamp}",timeout=60);response.raise_for_status();html=response.text
    checks={"section_2c9fbc0":'data-id="2c9fbc0"' not in html,"section_6e14dab":'data-id="6e14dab"' not in html,"ibm_logo":"logo-ibm21.png" not in html,"reviews":html.count('class="eit-google-reviews"')==1,"procurement":'data-id="c58e3f3"' in html}
    print(json.dumps({"status":response.status_code,"public":checks}))
    if not all(checks.values()):raise RuntimeError(f"Public verification failed: {checks}")


if __name__ == "__main__":
    main()
