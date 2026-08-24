"""Consolidate the legacy cybersecurity page into the Huntress canonical page."""

import ast
import json
import os
import re
import time
from pathlib import Path

import paramiko
import requests


HOST="198185.eu13.ssh.myftpupload.com";USER="client_c47ef96dfe_198185";ROOT="/home/client_c47ef96dfe_198185/html"
OLD_PAGE_ID=988509;CANONICAL_PAGE_ID=989829;MENU_ITEM_ID=988549
OLD_URL="https://everythingit.ie/business-continuity/information-security-services/cybersecurity-solutions-dublin/"
CANONICAL_URL="https://everythingit.ie/cybersecurity-dublin/"
CREDENTIAL_SOURCE=Path(os.environ.get("EIT_CREDENTIAL_SOURCE",r"C:\Users\yasir\Downloads\Other\eit_case_studies_rebuild.py"))


def load_password()->str:
    source=CREDENTIAL_SOURCE.read_text(encoding="utf-8-sig");match=re.search(r"^PASSWORD\s*=\s*(.+)$",source,re.MULTILINE)
    if not match:raise RuntimeError("Password assignment not found")
    password=ast.literal_eval(match.group(1).strip())
    if not isinstance(password,str) or not password:raise RuntimeError("Password is empty")
    return password


PHP=f'''<?php
require_once __DIR__ . '/wp-load.php';global $wpdb;
$oldId={OLD_PAGE_ID};$canonicalId={CANONICAL_PAGE_ID};$menuItemId={MENU_ITEM_ID};$oldUrl='{OLD_URL}';$canonicalUrl='{CANONICAL_URL}';
$old=get_post($oldId);$canonical=get_post($canonicalId);if(!$old||!$canonical){{throw new RuntimeException('Cybersecurity page missing');}}
if($canonical->post_status!=='publish'){{throw new RuntimeException('Canonical Huntress page is not published');}}
$terms=wp_get_object_terms($menuItemId,'nav_menu');if(is_wp_error($terms)||count($terms)!==1){{throw new RuntimeException('Menu location lookup failed');}}
$stamp=gmdate('Ymd-His');$backupDir='/home/client_c47ef96dfe_198185/eit-backups';if(!is_dir($backupDir)&&!mkdir($backupDir,0750,true)){{throw new RuntimeException('Backup directory unavailable');}}
$backupData=['old_page'=>get_post($oldId,ARRAY_A),'old_elementor'=>get_post_meta($oldId,'_elementor_data',true),'menu_meta'=>get_post_meta($menuItemId),'replacements'=>[]];
$contentRows=$wpdb->get_results($wpdb->prepare("SELECT ID,post_content FROM {{$wpdb->posts}} WHERE post_content LIKE %s",'%'.$wpdb->esc_like($oldUrl).'%'));
foreach($contentRows as $row){{$new=str_replace($oldUrl,$canonicalUrl,$row->post_content,$count);if($count){{$backupData['replacements'][]=['post_id'=>(int)$row->ID,'field'=>'post_content'];wp_update_post(['ID'=>(int)$row->ID,'post_content'=>$new]);}}}}
$metaRows=$wpdb->get_results($wpdb->prepare("SELECT post_id,meta_id,meta_value FROM {{$wpdb->postmeta}} WHERE meta_key='_elementor_data' AND meta_value LIKE %s",'%'.$wpdb->esc_like($oldUrl).'%'));
foreach($metaRows as $row){{$new=str_replace($oldUrl,$canonicalUrl,$row->meta_value,$count);if($count){{$backupData['replacements'][]=['post_id'=>(int)$row->post_id,'field'=>'_elementor_data'];update_metadata_by_mid('post',(int)$row->meta_id,wp_slash($new));}}}}
$menuResult=wp_update_nav_menu_item((int)$terms[0]->term_id,$menuItemId,['menu-item-object-id'=>$canonicalId,'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish','menu-item-title'=>'Cybersecurity Solutions']);
if(is_wp_error($menuResult)){{throw new RuntimeException($menuResult->get_error_message());}}
wp_update_post(['ID'=>$oldId,'post_status'=>'draft']);
$backup=$backupDir.'/cybersecurity-consolidation-'.$stamp.'.json';if(file_put_contents($backup,wp_json_encode($backupData,JSON_UNESCAPED_SLASHES))===false){{throw new RuntimeException('Backup write failed');}}
add_post_meta($oldId,'_eit_before_cybersecurity_consolidation_'.$stamp,wp_json_encode($backupData,JSON_UNESCAPED_SLASHES),true);
foreach(array_unique(array_merge([$oldId,$canonicalId],array_column($backupData['replacements'],'post_id'))) as $postId){{delete_post_meta($postId,'_elementor_element_cache');delete_post_meta($postId,'_elementor_css');clean_post_cache($postId);}}
wp_cache_flush();do_action('wpseo_invalidate_sitemap_cache');if(class_exists('Elementor\\Plugin')){{Elementor\\Plugin::$instance->files_manager->clear_cache();}}
if(isset($GLOBALS['wpaas_cache_class'])){{if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{$GLOBALS['wpaas_cache_class']->do_ban();}}if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$GLOBALS['wpaas_cache_class']->flush_cdn();}}}}
echo wp_json_encode(['old_status'=>get_post_status($oldId),'canonical_status'=>get_post_status($canonicalId),'menu_object_id'=>(int)get_post_meta($menuItemId,'_menu_item_object_id',true),'references_updated'=>count($backupData['replacements']),'backup'=>basename($backup)]);
'''


def main()->None:
    stamp=int(time.time());local=Path(__file__).resolve().parent/f".eit-cyber-consolidate-{stamp}.php";remote=local.name;local.write_text(PHP,encoding="utf-8",newline="\n")
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
    home=requests.get(f"https://everythingit.ie/?cyber-consolidation={stamp}",timeout=60);home.raise_for_status()
    old=requests.get(OLD_URL,allow_redirects=False,timeout=60);sitemap=requests.get(f"https://everythingit.ie/page-sitemap.xml?cyber-consolidation={stamp}",timeout=60);sitemap.raise_for_status()
    checks={"canonical_menu":f'href="{CANONICAL_URL}"' in home.text,"old_menu_absent":f'href="{OLD_URL}"' not in home.text,"old_redirect":old.status_code in (301,302) and old.headers.get("Location","").rstrip("/").endswith("/cybersecurity-dublin"),"old_not_sitemap":OLD_URL not in sitemap.text,"canonical_in_sitemap":CANONICAL_URL in sitemap.text}
    print(json.dumps({"home_status":home.status_code,"old_status":old.status_code,"sitemap_status":sitemap.status_code,"public":checks}))
    if not all(checks.values()):raise RuntimeError(f"Public verification failed: {checks}")


if __name__=="__main__":main()
