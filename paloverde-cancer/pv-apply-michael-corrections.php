<?php
/**
 * Apply Michael Bustard's 31 Aug 2026 correction list.
 * Run with: wp eval-file /tmp/pv-apply-michael-corrections.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$stamp      = gmdate( 'Ymd-His' );
$backup_dir = rtrim( getenv( 'HOME' ) ?: '/tmp', '/' ) . '/pv-michael-corrections-' . $stamp;
wp_mkdir_p( $backup_dir );

function pv_backup_post_fields( $id, $backup_dir ) {
	$post = get_post( $id );
	file_put_contents( $backup_dir . '/' . $id . '-post_content.html', $post->post_content );
	file_put_contents( $backup_dir . '/' . $id . '-elementor.json', (string) get_post_meta( $id, '_elementor_data', true ) );
}

foreach ( array( 73, 2159, 961, 533, 461, 501, 544 ) as $id ) {
	pv_backup_post_fields( $id, $backup_dir );
}

// 1. Add the missing Gilbert office image to the homepage location carousel.
$home_id   = 73;
$gto_url   = 'https://875051.us16.myftpupload.com/wp-content/uploads/2026/03/GTO-1-1488-W-Elliot-East-Valley.jpg';
$wvo_url   = 'https://875051.us16.myftpupload.com/wp-content/uploads/2025/12/WVO-9250-W-Thomas-1.jpg';
$home_data = (string) get_post_meta( $home_id, '_elementor_data', true );

if ( false === strpos( $home_data, $gto_url ) ) {
	$home_data = str_replace(
		'<img src=\\"' . $wvo_url . '\\" class=\\"carousel-image\\">',
		'<img src=\\"' . $wvo_url . '\\" class=\\"carousel-image\\">\n    <img src=\\"' . $gto_url . '\\" class=\\"carousel-image\\" alt=\\"Palo Verde East Valley office in Gilbert\\">',
		$home_data,
		$image_count
	);
	$home_data = str_replace(
		'<span class=\\"dot\\" data-index=\\"3\\"></span>',
		'<span class=\\"dot\\" data-index=\\"3\\"></span>\n        <span class=\\"dot\\" data-index=\\"4\\"></span>',
		$home_data,
		$dot_count
	);
	if ( 1 !== $image_count || 1 !== $dot_count ) {
		WP_CLI::error( "Homepage carousel markers did not match exactly (image=$image_count, dot=$dot_count)." );
	}
	update_post_meta( $home_id, '_elementor_data', wp_slash( $home_data ) );
}

// Keep the dormant post_content copy aligned if it contains the carousel.
$home_content = (string) get_post_field( 'post_content', $home_id );
if ( false !== strpos( $home_content, $wvo_url ) && false === strpos( $home_content, $gto_url ) ) {
	$home_content = str_replace(
		'<img src="' . $wvo_url . '" class="carousel-image">',
		'<img src="' . $wvo_url . '" class="carousel-image">\n    <img src="' . $gto_url . '" class="carousel-image" alt="Palo Verde East Valley office in Gilbert">',
		$home_content
	);
	$home_content = str_replace(
		'<span class="dot" data-index="3"></span>',
		'<span class="dot" data-index="3"></span>\n        <span class="dot" data-index="4"></span>',
		$home_content
	);
	wp_update_post( array( 'ID' => $home_id, 'post_content' => wp_slash( $home_content ) ) );
} else {
	wp_update_post( array( 'ID' => $home_id ) );
}

// 2. Replace the four old 160x111 location images with verified full-resolution files.
$location_images = array(
	533 => array( '2025/10/eestrella-small.png', '2025/12/WVO-9250-W-Thomas-1.jpg' ),
	461 => array( '2025/10/Glendale.jpg', '2025/12/TBO-5601-W-Eugie.jpg' ),
	501 => array( '2025/10/Scottsdalee.jpg', '2025/12/SDO-2-7373-N-Scottsdale.webp' ),
	544 => array( '2025/10/eEastValley.jpg', '2026/03/GTO-1-1488-W-Elliot-East-Valley.jpg' ),
);

foreach ( $location_images as $id => $paths ) {
	list( $old_path, $new_path ) = $paths;
	$post      = get_post( $id );
	$content   = str_replace( $old_path, $new_path, $post->post_content, $content_count );
	$elementor = (string) get_post_meta( $id, '_elementor_data', true );
	$elementor = str_replace( $old_path, $new_path, $elementor, $elementor_count );
	if ( $content_count < 1 ) {
		WP_CLI::error( "Location $id did not contain its expected low-resolution image." );
	}
	wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $content ) ) );
	if ( $elementor_count > 0 ) {
		update_post_meta( $id, '_elementor_data', wp_slash( $elementor ) );
	}
}

// 3. Rebuild Your Team so all six physicians, including Dr. Mamani, are present.
$team_html = <<<'HTML'
<section class="pv-team" aria-labelledby="pv-team-title">
<style>.pv-team{background:#050505;color:#fff;padding:72px 24px;font-family:Roboto,Arial,sans-serif}.pv-team__inner{max-width:1180px;margin:0 auto}.pv-team h1{font-size:clamp(34px,5vw,54px);line-height:1.1;margin:0 0 14px;color:#fff}.pv-team__intro{max-width:820px;color:#d9d9df;font-size:18px;line-height:1.7;margin:0 0 42px}.pv-team__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.pv-team__card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.28);display:flex;flex-direction:column}.pv-team__card img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover;object-position:center top}.pv-team__body{padding:22px;display:flex;flex-direction:column;flex:1}.pv-team__body h2{font-size:22px;line-height:1.25;color:#0f0a2a;margin:0 0 8px}.pv-team__role{color:#4a5568;font-size:14px;line-height:1.5;margin:0 0 20px}.pv-team__link{display:inline-flex;align-items:center;justify-content:center;margin-top:auto;background:#0f2a42;color:#fff!important;text-decoration:none!important;padding:12px 18px;border-radius:7px;font-weight:700}.pv-team__link:hover,.pv-team__link:focus{background:#007bff;color:#fff!important}@media(max-width:900px){.pv-team__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.pv-team{padding:52px 18px}.pv-team__grid{grid-template-columns:1fr}}</style>
<div class="pv-team__inner">
<h1 id="pv-team-title">Your Phoenix Area Cancer Team</h1>
<p class="pv-team__intro">At Palo Verde Cancer Specialists, our focus is on you and providing advanced, compassionate cancer care. Meet the physicians serving our locations across the Valley.</p>
<div class="pv-team__grid">
<article class="pv-team__card"><img src="https://875051.us16.myftpupload.com/wp-content/uploads/2025/10/Dr_nazish-Ahmad_new-headshot.jpg" alt="Dr. Nazish Ahmad"><div class="pv-team__body"><h2>Nazish Ahmad, DO</h2><p class="pv-team__role">Medical Oncology &amp; Hematology</p><a class="pv-team__link" href="/dr-nazish-ahmad/">View Profile</a></div></article>
<article class="pv-team__card"><img src="https://875051.us16.myftpupload.com/wp-content/uploads/2025/10/rajinder-Grover-web.jpg" alt="Dr. Rajinder Grover"><div class="pv-team__body"><h2>Rajinder Grover, MD</h2><p class="pv-team__role">Medical Oncology &amp; Hematology</p><a class="pv-team__link" href="/dr-rajinder-grover/">View Profile</a></div></article>
<article class="pv-team__card"><img src="https://875051.us16.myftpupload.com/wp-content/uploads/2025/10/Maqbool-A.-Halepota-web.jpg" alt="Dr. Maqbool Halepota"><div class="pv-team__body"><h2>Maqbool A. Halepota, MD</h2><p class="pv-team__role">Medical Oncology &amp; Hematology</p><a class="pv-team__link" href="/dr-maqbool-halepota/">View Profile</a></div></article>
<article class="pv-team__card"><img src="https://875051.us16.myftpupload.com/wp-content/uploads/2025/10/Demetrio-Mamani-web.jpg" alt="Dr. Demetrio Mamani"><div class="pv-team__body"><h2>Demetrio Mamani, MD</h2><p class="pv-team__role">Medical Oncology &amp; Hematology</p><a class="pv-team__link" href="/your-team/dr-mamani/">View Profile</a></div></article>
<article class="pv-team__card"><img src="https://875051.us16.myftpupload.com/wp-content/uploads/2025/10/amol-Rakkar-web.jpg" alt="Dr. Amol Rakkar"><div class="pv-team__body"><h2>Amol N.S. Rakkar, MD</h2><p class="pv-team__role">Medical Oncology &amp; Hematology</p><a class="pv-team__link" href="/dr-amol-rakkar/">View Profile</a></div></article>
<article class="pv-team__card"><img src="https://875051.us16.myftpupload.com/wp-content/uploads/2025/10/haider-Zafar-web.jpg" alt="Dr. Haider Zafar"><div class="pv-team__body"><h2>Haider Zafar, MD</h2><p class="pv-team__role">Medical Oncology &amp; Hematology</p><a class="pv-team__link" href="/dr-haider-zafar/">View Profile</a></div></article>
</div></div></section>
HTML;

wp_update_post( array( 'ID' => 2159, 'post_content' => wp_slash( $team_html ) ) );

// 4. Replace the malformed PET page with the same location-detail visual language,
// a dedicated PET map, and no unrelated all-location listing at the bottom.
$pet_html = <<<'HTML'
<div class="pv-pet-page">
<style>.pv-pet-page{background:#050505;color:#fff;font-family:Roboto,Arial,sans-serif}.pv-pet-page *{box-sizing:border-box}.pv-pet-hero{min-height:480px;display:flex;align-items:flex-end;background:linear-gradient(180deg,rgba(15,10,42,.2),rgba(5,5,5,.88)),url('https://875051.us16.myftpupload.com/wp-content/uploads/2025/12/PET-2-scaled.jpeg') center/cover no-repeat;padding:72px 24px}.pv-pet-wrap{width:min(1160px,100%);margin:0 auto}.pv-pet-hero h1{font-size:clamp(40px,6vw,66px);line-height:1.05;color:#fff;margin:0 0 14px}.pv-pet-hero p{font-size:20px;line-height:1.6;max-width:760px;color:#f4f2fb}.pv-pet-summary{padding:34px 24px;background:#f8f7ff;color:#1b1730}.pv-pet-summary__grid{display:grid;grid-template-columns:1fr auto;gap:28px;align-items:center}.pv-pet-summary h2{margin:0 0 10px;color:#0f0a2a;font-size:28px}.pv-pet-summary p{margin:0;color:#4a5568;font-size:17px;line-height:1.65}.pv-pet-btn{display:inline-flex;padding:13px 22px;background:#0f2a42;color:#fff!important;text-decoration:none!important;border-radius:7px;font-weight:700;white-space:nowrap}.pv-pet-btn:hover,.pv-pet-btn:focus{background:#007bff;color:#fff!important}.pv-pet-content{padding:72px 24px}.pv-pet-content__grid{display:grid;grid-template-columns:1.15fr .85fr;gap:46px;align-items:start}.pv-pet-card{background:#14121f;border:1px solid #2a2540;border-radius:14px;padding:30px}.pv-pet-card h2,.pv-pet-card h3{color:#fff;margin-top:0}.pv-pet-card h2{font-size:32px}.pv-pet-card h3{font-size:22px;margin-top:28px}.pv-pet-card p,.pv-pet-card li{color:#d9d9df;line-height:1.75;font-size:16px}.pv-pet-card ul{padding-left:22px}.pv-pet-card img{width:100%;border-radius:10px;margin-top:22px}.pv-pet-map{padding:0 24px 72px}.pv-pet-map h2{font-size:34px;margin:0 0 22px;color:#fff}.pv-pet-map iframe{width:100%;height:420px;border:0;border-radius:14px;display:block}.pv-pet-faq{padding:0 24px 72px}.pv-pet-faq h2{font-size:34px;color:#fff;margin:0 0 24px}.pv-pet-faq details{background:#14121f;border:1px solid #2a2540;border-radius:10px;padding:18px 20px;margin:12px 0}.pv-pet-faq summary{cursor:pointer;font-weight:700;font-size:17px}.pv-pet-faq details p{color:#d9d9df;line-height:1.7;margin:14px 0 0}@media(max-width:820px){.pv-pet-summary__grid,.pv-pet-content__grid{grid-template-columns:1fr}.pv-pet-hero{min-height:390px}.pv-pet-map iframe{height:340px}}</style>
<section class="pv-pet-hero"><div class="pv-pet-wrap"><h1>PET Scan Imaging</h1><p>Advanced imaging technology helping our specialists detect, plan, and treat with precision.</p></div></section>
<section class="pv-pet-summary"><div class="pv-pet-wrap pv-pet-summary__grid"><div><h2>Palo Verde PET Scan Imaging</h2><p>16641 N. 40th St., Phoenix, AZ 85032</p></div><a class="pv-pet-btn" href="https://www.google.com/maps/dir/?api=1&amp;destination=16641+N+40th+St+Phoenix,+AZ+85032" target="_blank" rel="noopener noreferrer">Get Directions</a></div></section>
<section class="pv-pet-content"><div class="pv-pet-wrap pv-pet-content__grid"><div class="pv-pet-card"><h2>What is a PET Scan?</h2><p>A positron emission tomography (PET) scan can be used alongside other cancer treatments. For some types of cancer, it helps identify where the cancer is located and its stage, helping your physician determine the most effective treatment plan.</p><h3>PET Scan at Palo Verde Cancer Specialists</h3><p>Our oncology specialists use PET imaging as part of comprehensive cancer care. Every PET scan includes:</p><ul><li>Same-day results whenever possible</li><li>Immediate appointments when available</li><li>Fast and convenient scheduling</li><li>Acceptance of major insurance plans</li></ul><p>We work diligently to provide results quickly so you and your physician can decide the next steps in your treatment.</p><a class="pv-pet-btn" href="/schedule/">Schedule an Appointment</a></div><aside class="pv-pet-card"><h2>Advanced Imaging, Compassionate Care</h2><p>Palo Verde Cancer Specialists is owned and operated by physicians who live in our community. Our team provides a caring environment and innovative services designed around each patient.</p><img src="https://875051.us16.myftpupload.com/wp-content/uploads/2025/10/Siemens-Biograph.jpg" alt="Siemens Biograph PET scanner at Palo Verde Cancer Specialists"></aside></div></section>
<section class="pv-pet-map"><div class="pv-pet-wrap"><h2>Find PET Scan Imaging</h2><iframe title="Map to Palo Verde PET Scan Imaging" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=16641+N+40th+St,+Phoenix,+AZ+85032&amp;output=embed"></iframe></div></section>
<section class="pv-pet-faq"><div class="pv-pet-wrap"><h2>PET Scan Frequently Asked Questions</h2><details><summary>What is the purpose of a PET scan?</summary><p>A PET scan helps your doctor detect cancer, determine its stage, and monitor how well treatment is working.</p></details><details><summary>Is a PET scan painful?</summary><p>No. The procedure involves a small injection of a radioactive tracer, followed by a rest period before the scan.</p></details><details><summary>How long does a PET scan take?</summary><p>The full process typically takes about one to two hours, including preparation. The scanning portion usually lasts between 20 and 40 minutes.</p></details><details><summary>Are there risks associated with PET scans?</summary><p>PET scans use a very small amount of radioactive tracer. Your physician will determine whether the examination is appropriate for you.</p></details><details><summary>When will I receive my results?</summary><p>Palo Verde provides same-day PET scan results whenever possible. Your physician will review the images and discuss next steps with you.</p></details></div></section>
</div>
HTML;

$pet_elementor = wp_json_encode( array( array(
	'id' => 'pvpet831',
	'elType' => 'container',
	'settings' => array( 'content_width' => 'full', 'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ) ),
	'elements' => array( array( 'id' => 'pvpet832', 'elType' => 'widget', 'settings' => array( 'html' => $pet_html ), 'elements' => array(), 'widgetType' => 'html' ) ),
	'isInner' => false,
) ) );

wp_update_post( array( 'ID' => 961, 'post_content' => wp_slash( $pet_html ) ) );
update_post_meta( 961, '_elementor_data', wp_slash( $pet_elementor ) );

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

WP_CLI::success( 'Applied Michael corrections. Backup: ' . $backup_dir );
