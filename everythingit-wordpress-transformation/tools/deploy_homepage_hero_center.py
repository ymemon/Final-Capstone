"""Center the EverythingIT homepage hero without changing its copy."""

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
WIDGET_ID = "5700064"
CREDENTIAL_SOURCE = Path(
    os.environ.get(
        "EIT_CREDENTIAL_SOURCE",
        r"C:\Users\yasir\Downloads\Other\eit_case_studies_rebuild.py",
    )
)

EXPECTED_COPY = (
    "Managed IT Services",
    "Solutions in Dublin, Ireland",
    "Unlock Your IT Potential",
    "Claim Your Complimentary Audit Today!",
    "Get Your Free Audit",
)

CENTER_CSS = """
/* AZWebCorp: centered homepage hero, 2026-08-24 */
.eit-hero-bg{justify-content:center}
.eit-hero-bg .eit-hero-content{width:100%;max-width:900px;margin:0 auto;text-align:center}
.eit-hero-bg p{margin:0 auto 30px}
""".strip()


def load_password() -> str:
    text = CREDENTIAL_SOURCE.read_text(encoding="utf-8-sig")
    match = re.search(r"^PASSWORD\s*=\s*(.+)$", text, re.MULTILINE)
    if not match:
        raise RuntimeError("Password assignment not found in external credential source")
    password = ast.literal_eval(match.group(1).strip())
    if not isinstance(password, str) or not password:
        raise RuntimeError("External password value is empty")
    return password


def build_php() -> str:
    css = CENTER_CSS.replace("\\", "\\\\").replace("'", "\\'")
    expected = json.dumps(EXPECTED_COPY)
    return f'''<?php
require_once __DIR__ . '/wp-load.php';
$id={PAGE_ID};$widgetId='{WIDGET_ID}';$css='{css}';
$expected=json_decode('{expected}',true);
$raw=get_post_meta($id,'_elementor_data',true);
$data=json_decode($raw,true);
if(!is_array($data)){{throw new RuntimeException('Elementor JSON is invalid');}}
$matches=0;$copyBefore=[];$copyAfter=[];
$edit=function(&$nodes)use(&$edit,$widgetId,$css,$expected,&$matches,&$copyBefore,&$copyAfter){{
  foreach($nodes as &$node){{
    if(($node['id']??'')===$widgetId){{
      $html=$node['settings']['html']??'';
      foreach($expected as $text){{$copyBefore[$text]=strpos(wp_strip_all_tags($html),$text)!==false;}}
      if(in_array(false,$copyBefore,true)){{throw new RuntimeException('Hero copy preflight failed');}}
      if(strpos($html,'centered homepage hero, 2026-08-24')===false){{
        $pos=strripos($html,'</style>');
        if($pos===false){{throw new RuntimeException('Hero style block not found');}}
        $html=substr($html,0,$pos)."\n".$css."\n".substr($html,$pos);
      }}
      $node['settings']['html']=$html;
      foreach($expected as $text){{$copyAfter[$text]=strpos(wp_strip_all_tags($html),$text)!==false;}}
      $matches++;
    }}
    if(!empty($node['elements'])){{$edit($node['elements']);}}
  }}
}};
$edit($data);
if($matches!==1){{throw new RuntimeException('Expected one hero widget; found '.$matches);}}
if($copyBefore!==$copyAfter || in_array(false,$copyAfter,true)){{throw new RuntimeException('Hero copy changed');}}
$stamp=gmdate('Ymd-His');
$backupDir=dirname(__DIR__).'/eit-backups';
if(!is_dir($backupDir)){{mkdir($backupDir,0750,true);}}
$backup=$backupDir.'/homepage-before-hero-center-'.$stamp.'.json';
file_put_contents($backup,wp_json_encode(['elementor_data'=>$raw],JSON_UNESCAPED_SLASHES));
add_post_meta($id,'_eit_home_before_hero_center_'.$stamp,$raw,true);
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
delete_post_meta($id,'_elementor_element_cache');delete_post_meta($id,'_elementor_css');clean_post_cache($id);wp_cache_flush();
if(class_exists('Elementor\\Plugin')){{Elementor\\Plugin::$instance->files_manager->clear_cache();}}
if(isset($GLOBALS['wpaas_cache_class'])){{
  if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{$GLOBALS['wpaas_cache_class']->do_ban();}}
  if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$GLOBALS['wpaas_cache_class']->flush_cdn();}}
}}
$render=Elementor\\Plugin::$instance->frontend->get_builder_content_for_display($id);
echo wp_json_encode(['page_id'=>$id,'matches'=>$matches,'backup'=>basename($backup),'center_css'=>strpos($render,'centered homepage hero, 2026-08-24')!==false,'copy_unchanged'=>$copyBefore===$copyAfter]);
'''


def main() -> None:
    stamp = int(time.time())
    local_payload = Path(__file__).resolve().parent / f".eit-hero-center-{stamp}.php"
    remote_name = local_payload.name
    local_payload.write_text(build_php(), encoding="utf-8", newline="\n")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=load_password(), timeout=30)
    sftp = ssh.open_sftp()
    try:
        sftp.put(str(local_payload), f"{ROOT}/{remote_name}")
        _, stdout, stderr = ssh.exec_command(
            f"cd {ROOT} && php -l {remote_name} && wp eval-file {remote_name}", timeout=240
        )
        output = stdout.read().decode("utf-8", "replace")
        error = stderr.read().decode("utf-8", "replace")
        print(output, end="")
        if error:
            print(error, end="")
        if stdout.channel.recv_exit_status() != 0:
            raise RuntimeError("Remote deployment failed")
    finally:
        try:
            sftp.remove(f"{ROOT}/{remote_name}")
        except OSError:
            pass
        sftp.close();ssh.close();local_payload.unlink(missing_ok=True)

    response = requests.get(f"https://everythingit.ie/?hero-center={stamp}", timeout=60)
    response.raise_for_status()
    checks = {text: text in response.text for text in EXPECTED_COPY}
    result = {"status": response.status_code, "center_css": "centered homepage hero, 2026-08-24" in response.text, "copy": checks}
    print(json.dumps({"public_verification": result}))
    if not result["center_css"] or not all(checks.values()):
        raise RuntimeError(f"Public verification failed: {result}")


if __name__ == "__main__":
    main()
