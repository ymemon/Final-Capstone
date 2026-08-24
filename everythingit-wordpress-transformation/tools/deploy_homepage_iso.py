"""Rollback-safe deployment of the EverythingIT homepage ISO/reviews band.

Credentials remain outside the repository. The local credential source can be
overridden with EIT_CREDENTIAL_SOURCE.
"""

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
TARGET_SECTION_ID = "d12903a"
SOURCE = Path(__file__).resolve().parents[1] / "src" / "components" / "homepage-iso-reviews.html"
CREDENTIAL_SOURCE = Path(
    os.environ.get(
        "EIT_CREDENTIAL_SOURCE",
        r"C:\Users\yasir\Downloads\Other\eit_case_studies_rebuild.py",
    )
)


def load_password() -> str:
    text = CREDENTIAL_SOURCE.read_text(encoding="utf-8-sig")
    match = re.search(r"^PASSWORD\s*=\s*(.+)$", text, re.MULTILINE)
    if not match:
        raise RuntimeError("Password assignment not found in external credential source")
    password = ast.literal_eval(match.group(1).strip())
    if not isinstance(password, str) or not password:
        raise RuntimeError("External password value is empty")
    return password


def build_php(source_name: str) -> str:
    return f'''<?php
require_once __DIR__ . '/wp-load.php';
$id={PAGE_ID};
$target='{TARGET_SECTION_ID}';
$source=__DIR__.'/{source_name}';
$html=file_get_contents($source);
if($html===false || strpos($html,'eit-iso-modern')===false || strpos($html,'Read all 40 reviews')===false){{
  throw new RuntimeException('Source validation failed');
}}
$raw=get_post_meta($id,'_elementor_data',true);
$data=json_decode($raw,true);
if(!is_array($data)){{throw new RuntimeException('Elementor JSON is invalid');}}
$matches=0;
$replace=function(&$nodes)use(&$replace,$target,$html,&$matches){{
  foreach($nodes as &$node){{
    if(($node['id']??'')===$target){{
      $node=[
        'id'=>$target,
        'elType'=>'section',
        'settings'=>[],
        'elements'=>[[
          'id'=>'eitiso1','elType'=>'column','settings'=>['_column_size'=>100,'_inline_size'=>null],
          'elements'=>[[
            'id'=>'eitiso2','elType'=>'widget','settings'=>['html'=>$html],
            'elements'=>[],'widgetType'=>'html'
          ]],'isInner'=>false
        ]],'isInner'=>false
      ];
      $matches++;
      continue;
    }}
    if(!empty($node['elements'])){{$replace($node['elements']);}}
  }}
}};
$replace($data);
if($matches!==1){{throw new RuntimeException('Expected one ISO section; found '.$matches);}}
$stamp=gmdate('Ymd-His');
$backupDir='/home/client_c47ef96dfe_198185/eit-backups';
if(!is_dir($backupDir)){{mkdir($backupDir,0750,true);}}
$backup=$backupDir.'/homepage-before-iso-reviews-'.$stamp.'.json';
file_put_contents($backup,wp_json_encode([
  'post_content'=>get_post_field('post_content',$id),
  'elementor_data'=>$raw,
  'elementor_page_settings'=>get_post_meta($id,'_elementor_page_settings',true),
],JSON_UNESCAPED_SLASHES));
add_post_meta($id,'_eit_home_before_iso_reviews_'.$stamp,$raw,true);
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
delete_post_meta($id,'_elementor_element_cache');
delete_post_meta($id,'_elementor_css');
clean_post_cache($id);
wp_cache_flush();
if(class_exists('Elementor\\Plugin')){{Elementor\\Plugin::$instance->files_manager->clear_cache();}}
$ban=null;$cdn=null;
if(isset($GLOBALS['wpaas_cache_class'])){{
  if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{$ban=$GLOBALS['wpaas_cache_class']->do_ban();}}
  if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$cdn=$GLOBALS['wpaas_cache_class']->flush_cdn();}}
}}
$render=Elementor\\Plugin::$instance->frontend->get_builder_content_for_display($id);
echo wp_json_encode([
  'page_id'=>$id,'matches'=>$matches,'backup'=>basename($backup),
  'new_iso'=>strpos($render,'eit-iso-modern')!==false,
  'all_reviews_link'=>strpos($render,'Read all 40 reviews')!==false,
  'old_iso_image'=>strpos($render,'iso-27001-certified-logo-it.jpg.png')!==false,
  'ban'=>$ban,'cdn'=>$cdn
]);
'''


def main() -> None:
    source = SOURCE.read_text(encoding="utf-8-sig")
    if source.count('class="eit-iso-modern"') != 1:
        raise RuntimeError("Expected exactly one modern ISO section")
    if source.count("Read all 40 reviews") != 1:
        raise RuntimeError("Expected exactly one all-reviews CTA")

    stamp = int(time.time())
    remote_source = f"eit-home-iso-{stamp}.html"
    remote_php = f"eit-home-iso-{stamp}.php"
    local_php = SOURCE.parent / f".{remote_php}"
    local_php.write_text(build_php(remote_source), encoding="utf-8", newline="\n")

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=load_password(), timeout=30)
    sftp = ssh.open_sftp()
    try:
        sftp.put(str(SOURCE), f"{ROOT}/{remote_source}")
        sftp.put(str(local_php), f"{ROOT}/{remote_php}")
        command = f"cd {ROOT} && php -l {remote_php} && wp eval-file {remote_php}"
        _, stdout, stderr = ssh.exec_command(command, timeout=240)
        output = stdout.read().decode("utf-8", "replace")
        error = stderr.read().decode("utf-8", "replace")
        print(output, end="")
        if error:
            print(error, end="")
        if stdout.channel.recv_exit_status() != 0:
            raise RuntimeError("Remote deployment failed")
    finally:
        for remote in (f"{ROOT}/{remote_source}", f"{ROOT}/{remote_php}"):
            try:
                sftp.remove(remote)
            except OSError:
                pass
        sftp.close()
        ssh.close()
        local_php.unlink(missing_ok=True)

    response = requests.get(f"https://everythingit.ie/?eitverify={stamp}", timeout=60)
    response.raise_for_status()
    verification = {
        "status": response.status_code,
        "new_iso": "eit-iso-modern" in response.text,
        "all_reviews_link": "Read all 40 reviews" in response.text,
        "old_iso_image": "iso-27001-certified-logo-it.jpg.png" in response.text,
    }
    print(json.dumps({"public_verification": verification}))
    if not verification["new_iso"] or not verification["all_reviews_link"] or verification["old_iso_image"]:
        raise RuntimeError(f"Public verification failed: {verification}")


if __name__ == "__main__":
    main()
