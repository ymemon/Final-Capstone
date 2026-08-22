"""Build a rollback-safe WordPress payload for the EverythingIT Our Team page.

This generator contains no server credentials. It reads the version-controlled page
source, validates important SEO/accessibility invariants, and emits a temporary PHP
payload for execution from an authorised WordPress root.
"""

import base64
import json
import re
import sys
from pathlib import Path


SOURCE = Path(__file__).resolve().parents[1] / "src" / "elementor-pages" / "our-team.html"
PAGE_ID = 1295
CANONICAL = "https://everythingit.ie/our-team/"


raw = SOURCE.read_text(encoding="utf-8-sig")
start = raw.find("<style>")
if start < 0:
    raise SystemExit("No <style> block found in Our Team source")
html = raw[start:].strip()

images = re.findall(r"<img\b[^>]*>", html, re.I)
audit = {
    "h1": len(re.findall(r"<h1\b", html, re.I)),
    "h2": len(re.findall(r"<h2\b", html, re.I)),
    "images": len(images),
    "images_with_alt": sum(bool(re.search(r"\balt\s*=", tag, re.I)) for tag in images),
    "scripts": len(re.findall(r"<script\b", html, re.I)),
    "html_bytes": len(html.encode("utf-8")),
}
if audit["h1"] != 1 or audit["images"] != audit["images_with_alt"]:
    raise SystemExit(f"Preflight audit failed: {audit}")

payload = base64.b64encode(html.encode("utf-8")).decode("ascii")
php = f'''<?php
require_once __DIR__ . '/wp-load.php';
$id={PAGE_ID};
$html=base64_decode('{payload}');
$old=get_post($id);
$backup='_eit_our_team_before_rebuild_20260821';
if(!metadata_exists('post',$id,$backup)){{
  add_post_meta($id,$backup,wp_json_encode([
    'post_content'=>$old->post_content,
    'elementor_data'=>get_post_meta($id,'_elementor_data',true),
    'title'=>$old->post_title,
    'slug'=>$old->post_name,
    'status'=>$old->post_status,
    'yoast_title'=>get_post_meta($id,'_yoast_wpseo_title',true),
    'yoast_desc'=>get_post_meta($id,'_yoast_wpseo_metadesc',true),
    'yoast_canonical'=>get_post_meta($id,'_yoast_wpseo_canonical',true),
  ]));
}}
$data=[["id"=>"eitte001","elType"=>"section","settings"=>[],"elements"=>[["id"=>"eitte002","elType"=>"column","settings"=>["_column_size"=>100,"_inline_size"=>null],"elements"=>[["id"=>"eitte003","elType"=>"widget","settings"=>["html"=>$html],"elements"=>[],"widgetType"=>"html"]],"isInner"=>false]],"isInner"=>false]];
wp_update_post(['ID'=>$id,'post_title'=>'Our Team','post_name'=>'our-team','post_content'=>$html,'post_status'=>'publish']);
global $wpdb;
$wpdb->update($wpdb->posts,['post_content'=>$html],['ID'=>$id],['%s'],['%d']);
update_post_meta($id,'_elementor_edit_mode','builder');
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
update_post_meta($id,'_elementor_page_settings',['hide_title'=>'yes']);
update_post_meta($id,'_yoast_wpseo_title','Meet Our IT Leadership Team Dublin | Everything IT');
update_post_meta($id,'_yoast_wpseo_metadesc','Meet the Everything IT leadership team delivering managed services, cloud, cybersecurity, procurement and information-security expertise from Dublin.');
update_post_meta($id,'_yoast_wpseo_canonical','{CANONICAL}');
delete_post_meta($id,'_elementor_element_cache');
delete_post_meta($id,'_elementor_css');
clean_post_cache($id);
wp_cache_flush();
$page_cache_purged=null;
$cdn_invalidation_id=null;
if(isset($GLOBALS['wpaas_cache_class'])){{
  if(method_exists($GLOBALS['wpaas_cache_class'],'do_ban')){{
    $page_cache_purged=$GLOBALS['wpaas_cache_class']->do_ban();
  }}
  if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{
    $cdn_invalidation_id=$GLOBALS['wpaas_cache_class']->flush_cdn();
  }}
}}
echo wp_json_encode([
  'id'=>$id,
  'status'=>get_post_status($id),
  'slug'=>get_post_field('post_name',$id),
  'content_bytes'=>strlen(get_post_field('post_content',$id)),
  'h1'=>substr_count(get_post_field('post_content',$id),'<h1'),
  'canonical'=>get_post_meta($id,'_yoast_wpseo_canonical',true),
  'backup'=>metadata_exists('post',$id,$backup),
  'page_cache_purged'=>$page_cache_purged,
  'cdn_invalidation_id'=>$cdn_invalidation_id,
]);
'''

if len(sys.argv) != 2:
    print(json.dumps({"source": str(SOURCE), "audit": audit}, indent=2))
    raise SystemExit(0)

output = Path(sys.argv[1]).resolve()
output.write_text(php, encoding="utf-8", newline="\n")
print(json.dumps({"payload": str(output), "php_bytes": len(php.encode()), "audit": audit}, indent=2))
