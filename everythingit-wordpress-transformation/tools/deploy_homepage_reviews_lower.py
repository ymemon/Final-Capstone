"""Move the EverythingIT Google-review slider to the end of the homepage."""

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
ISO_SECTION_ID = "d12903a"
REVIEWS_SECTION_ID = "eitrev01"
AFTER_SECTION_ID = "c58e3f3"
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
$id={PAGE_ID};$source=__DIR__.'/{source_name}';
$full=file_get_contents($source);
if($full===false){{throw new RuntimeException('Source missing');}}
if(!preg_match('~<style>.*?</style>~s',$full,$styleMatch)){{throw new RuntimeException('Style block missing');}}
if(!preg_match('~<section class="eit-google-reviews".*?</section>~s',$full,$reviewMatch)){{throw new RuntimeException('Reviews section missing');}}
if(!preg_match('~<section class="eit-iso-modern".*?</section>~s',$full,$isoMatch)){{throw new RuntimeException('ISO section missing');}}
$isoHtml=$styleMatch[0]."\n".$isoMatch[0];$reviewsHtml=$reviewMatch[0];
if(substr_count($reviewsHtml,'class="eit-google-card"')!==20){{throw new RuntimeException('Expected 20 rendered review cards');}}
if(substr_count($reviewsHtml,'id="eit-review-')!==10){{throw new RuntimeException('Expected 10 unique review cards');}}
if(substr_count($reviewsHtml,'eit-google-set--duplicate')!==1){{throw new RuntimeException('Expected one accessible-hidden duplicate set');}}
if(strpos($full,'eitGoogleMarquee')===false){{throw new RuntimeException('Continuous slider animation missing');}}
$raw=get_post_meta($id,'_elementor_data',true);$data=json_decode($raw,true);
if(!is_array($data)){{throw new RuntimeException('Elementor JSON invalid');}}
$isoMatches=0;$anchorMatches=0;
$clean=[];
foreach($data as $node){{
  if(($node['id']??'')==='{REVIEWS_SECTION_ID}'){{continue;}}
  if(($node['id']??'')==='{ISO_SECTION_ID}'){{
    $node=['id'=>'{ISO_SECTION_ID}','elType'=>'section','settings'=>[],'elements'=>[[
      'id'=>'eitiso1','elType'=>'column','settings'=>['_column_size'=>100,'_inline_size'=>null],
      'elements'=>[['id'=>'eitiso2','elType'=>'widget','settings'=>['html'=>$isoHtml],'elements'=>[],'widgetType'=>'html']],
      'isInner'=>false]],'isInner'=>false];
    $isoMatches++;
  }}
  $clean[]=$node;
}}
if($isoMatches!==1){{throw new RuntimeException('Expected one ISO section; found '.$isoMatches);}}
$reviewsNode=['id'=>'{REVIEWS_SECTION_ID}','elType'=>'section','settings'=>[],'elements'=>[[
  'id'=>'eitrev02','elType'=>'column','settings'=>['_column_size'=>100,'_inline_size'=>null],
  'elements'=>[['id'=>'eitrev03','elType'=>'widget','settings'=>['html'=>$reviewsHtml],'elements'=>[],'widgetType'=>'html']],
  'isInner'=>false]],'isInner'=>false];
$data=[];
foreach($clean as $node){{
  $data[]=$node;
  if(($node['id']??'')==='{AFTER_SECTION_ID}'){{$data[]=$reviewsNode;$anchorMatches++;}}
}}
if($anchorMatches!==1){{throw new RuntimeException('Expected one lower-page anchor; found '.$anchorMatches);}}
$stamp=gmdate('Ymd-His');$backupDir='/home/client_c47ef96dfe_198185/eit-backups';
if(!is_dir($backupDir)){{mkdir($backupDir,0750,true);}}
$backup=$backupDir.'/homepage-before-review-relocation-'.$stamp.'.json';
file_put_contents($backup,wp_json_encode(['elementor_data'=>$raw],JSON_UNESCAPED_SLASHES));
add_post_meta($id,'_eit_home_before_review_relocation_'.$stamp,$raw,true);
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
delete_post_meta($id,'_elementor_element_cache');delete_post_meta($id,'_elementor_css');clean_post_cache($id);wp_cache_flush();
if(class_exists('Elementor\\Plugin')){{Elementor\\Plugin::$instance->files_manager->clear_cache();}}
if(isset($GLOBALS['wpaas_cache_class'])){{
 if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{$GLOBALS['wpaas_cache_class']->do_ban();}}
 if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$GLOBALS['wpaas_cache_class']->flush_cdn();}}
}}
$render=Elementor\\Plugin::$instance->frontend->get_builder_content_for_display($id);
echo wp_json_encode(['page_id'=>$id,'backup'=>basename($backup),'iso_sections'=>substr_count($render,'class="eit-iso-modern"'),'review_sections'=>substr_count($render,'class="eit-google-reviews"'),'cards'=>substr_count($render,'class="eit-google-card"'),'unique_cards'=>substr_count($render,'id="eit-review-'),'duplicate_sets'=>substr_count($render,'eit-google-set--duplicate'),'continuous'=>strpos($render,'eitGoogleMarquee')!==false]);
'''


def main() -> None:
    source = SOURCE.read_text(encoding="utf-8-sig")
    if (source.count('class="eit-google-card"') != 20
            or source.count('id="eit-review-') != 10
            or source.count('eit-google-set--duplicate') != 2
            or 'eitGoogleMarquee' not in source):
        raise RuntimeError("Source slider validation failed")
    stamp = int(time.time())
    remote_source = f"eit-review-lower-{stamp}.html"
    remote_php = f"eit-review-lower-{stamp}.php"
    local_php = SOURCE.parent / f".{remote_php}"
    local_php.write_text(build_php(remote_source), encoding="utf-8", newline="\n")
    ssh = paramiko.SSHClient();ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=load_password(), timeout=30)
    sftp = ssh.open_sftp()
    try:
        sftp.put(str(SOURCE), f"{ROOT}/{remote_source}");sftp.put(str(local_php), f"{ROOT}/{remote_php}")
        _, stdout, stderr = ssh.exec_command(f"cd {ROOT} && php -l {remote_php} && wp eval-file {remote_php}",timeout=240)
        output=stdout.read().decode('utf-8','replace');error=stderr.read().decode('utf-8','replace')
        print(output,end='');print(error,end='') if error else None
        if stdout.channel.recv_exit_status()!=0:raise RuntimeError('Remote deployment failed')
    finally:
        for name in (remote_source,remote_php):
            try:sftp.remove(f"{ROOT}/{name}")
            except OSError:pass
        sftp.close();ssh.close();local_php.unlink(missing_ok=True)
    response=requests.get(f"https://everythingit.ie/?review-lower={stamp}",timeout=60);response.raise_for_status()
    checks={'cards':response.text.count('class="eit-google-card"'),'unique_cards':response.text.count('id="eit-review-'),'duplicate_sets':response.text.count('class="eit-google-set eit-google-set--duplicate"'),'continuous':'eitGoogleMarquee' in response.text,'iso':response.text.count('class="eit-iso-modern"'),'reviews':response.text.count('class="eit-google-reviews"'),'tablet_frame':'grid-column:1/-1' in response.text}
    print(json.dumps({'status':response.status_code,'public':checks}))
    if checks!={'cards':20,'unique_cards':10,'duplicate_sets':1,'continuous':True,'iso':1,'reviews':1,'tablet_frame':True}:raise RuntimeError(f'Public verification failed: {checks}')


if __name__ == '__main__':
    main()
