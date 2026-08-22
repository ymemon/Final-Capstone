import base64
import json
import re
import sys
from pathlib import Path

SOURCE = Path(r"C:\Users\yasir\Downloads\EVERYTHINGIT_NEW_MDM_PAGE_ELEMENTOR_CODE.txt")
PAGE_ID = 989685

raw = SOURCE.read_text(encoding="utf-8")
start = raw.find("<style>")
if start < 0:
    raise SystemExit("No <style> block found in source file")
html = raw[start:].strip()

# Every inline SVG in this design is decorative and has adjacent visible text.
# Keep it out of the accessibility tree consistently and prevent legacy focus.
html = re.sub(r"<svg(?![^>]*\baria-hidden=)", '<svg aria-hidden="true" focusable="false"', html)
html = re.sub(r'<svg aria-hidden="true"(?![^>]*\bfocusable=)', '<svg aria-hidden="true" focusable="false"', html)

audit = {
    "h1": len(re.findall(r"<h1\b", html, re.I)),
    "h2": len(re.findall(r"<h2\b", html, re.I)),
    "img": len(re.findall(r"<img\b", html, re.I)),
    "svg": len(re.findall(r"<svg\b", html, re.I)),
    "decorative_svg": len(re.findall(r'<svg[^>]*aria-hidden="true"[^>]*focusable="false"', html, re.I)),
    "faq_schema": html.count('"@type": "FAQPage"'),
    "html_bytes": len(html.encode("utf-8")),
}
if audit["h1"] != 1 or audit["svg"] != audit["decorative_svg"] or audit["faq_schema"] != 1:
    raise SystemExit(f"Audit failed: {audit}")

payload = base64.b64encode(html.encode("utf-8")).decode("ascii")
php = f'''<?php
require_once __DIR__ . '/wp-load.php';
$id={PAGE_ID};
$html=base64_decode('{payload}');
$old=get_post($id);
$old_data=get_post_meta($id,'_elementor_data',true);
$backup='_eit_mdm_before_rebuild_20260821';
if(!metadata_exists('post',$id,$backup)){{
  add_post_meta($id,$backup,wp_json_encode([
    'post_content'=>$old->post_content,
    'elementor_data'=>$old_data,
    'title'=>$old->post_title,
    'slug'=>$old->post_name,
    'status'=>$old->post_status,
    'yoast_title'=>get_post_meta($id,'_yoast_wpseo_title',true),
    'yoast_desc'=>get_post_meta($id,'_yoast_wpseo_metadesc',true),
    'yoast_canonical'=>get_post_meta($id,'_yoast_wpseo_canonical',true),
  ]));
}}
$data=[["id"=>"eitmdm1","elType"=>"section","settings"=>[],"elements"=>[["id"=>"eitmdm2","elType"=>"column","settings"=>["_column_size"=>100,"_inline_size"=>null],"elements"=>[["id"=>"eitmdm3","elType"=>"widget","settings"=>["html"=>$html],"elements"=>[],"widgetType"=>"html"]],"isInner"=>false]],"isInner"=>false]];
wp_update_post(['ID'=>$id,'post_title'=>'Microsoft Intune & MDM Services Dublin | Everything IT','post_name'=>'mdm-services-dublin','post_content'=>$html,'post_status'=>'publish']);
global $wpdb;
$wpdb->update($wpdb->posts,['post_content'=>$html],['ID'=>$id],['%s'],['%d']);
update_post_meta($id,'_elementor_edit_mode','builder');
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
update_post_meta($id,'_elementor_page_settings',['hide_title'=>'yes']);
update_post_meta($id,'_yoast_wpseo_title','Microsoft Intune & MDM Services Dublin | Everything IT');
update_post_meta($id,'_yoast_wpseo_metadesc','Secure company and BYOD phones, tablets and laptops with Microsoft Intune MDM services in Dublin, Cork, Galway, Limerick and Waterford.');
update_post_meta($id,'_yoast_wpseo_canonical','https://everythingit.ie/mdm-services-dublin/');
delete_post_meta($id,'_elementor_element_cache');
delete_post_meta($id,'_elementor_css');
clean_post_cache($id);
wp_cache_flush();
if(isset($GLOBALS['wpaas_cache_class'])){{
  if(method_exists($GLOBALS['wpaas_cache_class'],'ban')){{$GLOBALS['wpaas_cache_class']->ban();}}
  if(method_exists($GLOBALS['wpaas_cache_class'],'flush_cdn')){{$GLOBALS['wpaas_cache_class']->flush_cdn();}}
}}
echo wp_json_encode([
 'id'=>$id,
 'status'=>get_post_status($id),
 'slug'=>get_post_field('post_name',$id),
 'title'=>get_the_title($id),
 'content_bytes'=>strlen(get_post_field('post_content',$id)),
 'h1'=>substr_count(get_post_field('post_content',$id),'<h1'),
 'canonical'=>get_post_meta($id,'_yoast_wpseo_canonical',true),
 'backup'=>metadata_exists('post',$id,$backup)
]);
'''

if len(sys.argv) != 2:
    print(json.dumps({"audit": audit}, indent=2))
    raise SystemExit(0)

out = Path(sys.argv[1])
out.write_text(php, encoding="utf-8", newline="\n")
print(json.dumps({"payload": str(out), "php_bytes": len(php.encode()), "audit": audit}, indent=2))
