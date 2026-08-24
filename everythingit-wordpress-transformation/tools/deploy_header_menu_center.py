"""Centre the EverythingIT global desktop/tablet navigation menu."""

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
HEADER_ID = 989508
MENU_WIDGET_ID = "63c90f5"
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
$id={HEADER_ID};$widgetId='{MENU_WIDGET_ID}';
$raw=get_post_meta($id,'_elementor_data',true);$data=json_decode($raw,true);
if(!is_array($data)){{throw new RuntimeException('Header Elementor JSON is invalid');}}
$matches=0;$before=null;
$edit=function(&$nodes)use(&$edit,$widgetId,&$matches,&$before){{
  foreach($nodes as &$node){{
    if(($node['id']??'')===$widgetId){{
      $before=$node['settings']['align']??'left';
      unset($node['settings']['menu_align']);
      $node['settings']['align']='center';
      $node['settings']['align_tablet']='center';
      $node['settings']['custom_css']="/* EIT centred global menu */\nselector .hfe-nav-menu,selector nav>ul{{justify-content:center!important}}";
      $matches++;
    }}
    if(!empty($node['elements'])){{$edit($node['elements']);}}
  }}
}};
$edit($data);
if($matches!==1){{throw new RuntimeException('Expected one menu widget; found '.$matches);}}
$stamp=gmdate('Ymd-His');$backupDir='/home/client_c47ef96dfe_198185/eit-backups';
if(!is_dir($backupDir)&&!mkdir($backupDir,0750,true)){{throw new RuntimeException('Backup directory unavailable');}}
$backup=$backupDir.'/header-before-menu-center-'.$stamp.'.json';
if(file_put_contents($backup,wp_json_encode(['elementor_data'=>$raw],JSON_UNESCAPED_SLASHES))===false){{throw new RuntimeException('Backup write failed');}}
add_post_meta($id,'_eit_header_before_menu_center_'.$stamp,$raw,true);
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
delete_post_meta($id,'_elementor_element_cache');delete_post_meta($id,'_elementor_css');clean_post_cache($id);wp_cache_flush();
if(class_exists('Elementor\\Plugin')){{Elementor\\Plugin::$instance->files_manager->clear_cache();}}
if(isset($GLOBALS['wpaas_cache_class'])){{
 if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{$GLOBALS['wpaas_cache_class']->do_ban();}}
 if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$GLOBALS['wpaas_cache_class']->flush_cdn();}}
}}
$render=Elementor\\Plugin::$instance->frontend->get_builder_content_for_display($id);
echo wp_json_encode(['header_id'=>$id,'widget_matches'=>$matches,'before'=>$before,'center_rule_saved'=>strpos(wp_json_encode($data),'EIT centred global menu')!==false,'backup'=>basename($backup)]);
'''


def main() -> None:
    stamp = int(time.time())
    local_payload = Path(__file__).resolve().parent / f".eit-header-center-{stamp}.php"
    remote_name = local_payload.name
    local_payload.write_text(PHP, encoding="utf-8", newline="\n")
    ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    sftp = None
    try:
        ssh.connect(HOST, username=USER, password=load_password(), timeout=30)
        sftp = ssh.open_sftp(); sftp.put(str(local_payload), f"{ROOT}/{remote_name}")
        _, stdout, stderr = ssh.exec_command(f"cd {ROOT} && php -l {remote_name} && wp eval-file {remote_name}", timeout=240)
        output=stdout.read().decode("utf-8","replace"); error=stderr.read().decode("utf-8","replace")
        print(output,end=""); print(error,end="")
        if stdout.channel.recv_exit_status()!=0:
            raise RuntimeError("Remote deployment failed")
    finally:
        if sftp is not None:
            try:sftp.remove(f"{ROOT}/{remote_name}")
            except OSError:pass
            sftp.close()
        ssh.close();local_payload.unlink(missing_ok=True)

    response=requests.get(f"https://everythingit.ie/?header-center={stamp}",timeout=60);response.raise_for_status()
    css_response=requests.get(f"https://everythingit.ie/wp-content/uploads/elementor/css/post-{HEADER_ID}.css?header-center={stamp}",timeout=60);css_response.raise_for_status()
    checks={
        "center_rule":"elementor-element-63c90f5 .hfe-nav-menu" in css_response.text and "justify-content:center!important" in css_response.text,
        "menu_present":'id="menu-1-63c90f5"' in response.text,
        "helpdesk":">Helpdesk<" in response.text,
    }
    print(json.dumps({"status":response.status_code,"css_status":css_response.status_code,"public":checks}))
    if not all(checks.values()):raise RuntimeError(f"Public verification failed: {checks}")


if __name__ == "__main__":
    main()
