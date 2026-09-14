<?php
/** Rebuild the four malformed location pages using one verified shared template. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$base = 'https://875051.us16.myftpupload.com';
$doctors = array(
	'ahmad'    => array( 'Nazish Ahmad, DO', 'Dr_nazish-Ahmad_new-headshot.jpg', '/dr-nazish-ahmad/' ),
	'grover'   => array( 'Rajinder Grover, MD', 'rajinder-Grover-web.jpg', '/dr-rajinder-grover/' ),
	'halepota' => array( 'Maqbool A. Halepota, MD', 'Maqbool-A.-Halepota-web.jpg', '/dr-maqbool-halepota/' ),
	'mamani'   => array( 'Demetrio Mamani, MD', 'Demetrio-Mamani-web.jpg', '/your-team/dr-mamani/' ),
	'rakkar'   => array( 'Amol N.S. Rakkar, MD', 'amol-Rakkar-web.jpg', '/dr-amol-rakkar/' ),
	'zafar'    => array( 'Haider Zafar, MD', 'haider-Zafar-web.jpg', '/dr-haider-zafar/' ),
);

$locations = array(
	533 => array(
		'name' => 'Estrella', 'subtitle' => 'Medical Oncology Excellence in Estrella',
		'address' => '9250 W. Thomas Rd., Ste. 150, Phoenix, AZ 85037', 'phone' => '623-478-8091', 'tel' => '6234788091',
		'image' => '2025/12/WVO-9250-W-Thomas-1.jpg', 'map' => '9250 W Thomas Rd Suite 150 Phoenix AZ 85037',
		'roster' => array( 'zafar', 'mamani', 'rakkar' ),
	),
	461 => array(
		'name' => 'Glendale', 'subtitle' => 'Medical Oncology Excellence in Glendale',
		'address' => '5601 W. Eugie Ave., Suite 106, Glendale, AZ 85304', 'phone' => '602-978-6255', 'tel' => '6029786255',
		'image' => '2025/12/TBO-5601-W-Eugie.jpg', 'map' => '5601 W Eugie Ave Suite 106 Glendale AZ 85304',
		'roster' => array( 'rakkar', 'mamani', 'grover', 'ahmad' ),
	),
	501 => array(
		'name' => 'Scottsdale', 'subtitle' => 'Medical Oncology Excellence in Scottsdale',
		'address' => '7373 N. Scottsdale Rd., Suite E-100, Scottsdale, AZ 85253', 'phone' => '480-941-1211', 'tel' => '4809411211',
		'image' => '2025/12/SDO-2-7373-N-Scottsdale.webp', 'map' => '7373 N Scottsdale Rd Suite E-100 Scottsdale AZ 85253',
		'roster' => array( 'halepota', 'grover', 'ahmad' ),
	),
	544 => array(
		'name' => 'East Valley (Gilbert)', 'subtitle' => 'Medical Oncology Excellence in Gilbert',
		'address' => '1488 W. Elliot Rd., Gilbert, AZ 85233', 'phone' => '480-941-1211', 'tel' => '4809411211',
		'image' => '2026/03/GTO-1-1488-W-Elliot-East-Valley.jpg', 'map' => '1488 W Elliot Rd Gilbert AZ 85233',
		'roster' => array( 'grover', 'halepota' ),
	),
);

$css = <<<'CSS'
<style>.pv-location{background:#050505;color:#fff;font-family:Roboto,Arial,sans-serif}.pv-location *{box-sizing:border-box}.pvl-wrap{width:min(1160px,100%);margin:0 auto}.pvl-hero{min-height:460px;display:flex;align-items:flex-end;padding:68px 24px;background-position:center;background-size:cover;background-repeat:no-repeat}.pvl-hero h1{font-size:clamp(40px,6vw,64px);line-height:1.05;color:#fff;margin:0 0 12px}.pvl-hero p{font-size:20px;color:#f3f0fa;margin:0}.pvl-summary{background:#f8f7ff;color:#172033;padding:32px 24px}.pvl-summary-grid{display:grid;grid-template-columns:1fr auto;align-items:center;gap:24px}.pvl-summary h2{color:#0f0a2a;font-size:28px;margin:0 0 8px}.pvl-summary p{color:#536174;margin:3px 0}.pvl-actions{display:flex;gap:10px;flex-wrap:wrap}.pvl-btn{display:inline-flex;align-items:center;justify-content:center;background:#0f2a42;color:#fff!important;text-decoration:none!important;padding:13px 20px;border-radius:7px;font-weight:700}.pvl-btn:hover,.pvl-btn:focus{background:#0879d9;color:#fff!important}.pvl-section{padding:68px 24px}.pvl-section.alt{background:#0e0d16}.pvl-grid-two{display:grid;grid-template-columns:1fr 1fr;gap:38px;align-items:center}.pvl-office-img{width:100%;border-radius:14px;display:block;box-shadow:0 10px 28px rgba(0,0,0,.35)}.pvl-copy h2,.pvl-section-title{color:#fff;font-size:34px;margin:0 0 18px}.pvl-copy p,.pvl-copy li{color:#d9d9df;font-size:16px;line-height:1.75}.pvl-copy ul{padding-left:20px}.pvl-doctors{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}.pvl-doctor{background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-direction:column}.pvl-doctor img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover;object-position:center top}.pvl-doctor-body{padding:18px;display:flex;flex-direction:column;flex:1}.pvl-doctor h3{color:#0f0a2a;font-size:19px;line-height:1.25;margin:0 0 7px}.pvl-doctor p{color:#536174;font-size:13px;margin:0 0 16px}.pvl-doctor a{margin-top:auto;color:#0879d9;font-weight:700;text-decoration:none}.pvl-services{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.pvl-service{background:#171522;border:1px solid #2a2740;border-radius:12px;padding:24px}.pvl-service h3{font-size:20px;color:#fff;margin:0 0 10px}.pvl-service p{color:#cfd2db;line-height:1.65;margin:0}.pvl-map iframe{width:100%;height:420px;border:0;border-radius:14px;display:block}.pvl-contact{background:#f8f7ff;color:#172033;border-radius:14px;padding:30px;display:grid;grid-template-columns:1fr auto;gap:22px;align-items:center}.pvl-contact h2{color:#0f0a2a;margin:0 0 8px}.pvl-contact p{color:#536174;margin:0}.pvl-contact-actions{display:flex;gap:10px;flex-wrap:wrap}.pvl-note{color:#aeb5c2;font-size:14px;margin-top:14px}@media(max-width:900px){.pvl-doctors,.pvl-services{grid-template-columns:repeat(2,minmax(0,1fr))}.pvl-grid-two{grid-template-columns:1fr}}@media(max-width:650px){.pvl-hero{min-height:360px;padding:45px 18px}.pvl-summary,.pvl-section{padding-left:18px;padding-right:18px}.pvl-summary-grid,.pvl-contact{grid-template-columns:1fr}.pvl-doctors,.pvl-services{grid-template-columns:1fr}.pvl-actions,.pvl-contact-actions{flex-direction:column;align-items:stretch}.pvl-btn{width:100%}.pvl-map iframe{height:330px}}</style>
CSS;

$backup_dir = rtrim( getenv( 'HOME' ) ?: '/tmp', '/' ) . '/pv-location-clean-rebuild-' . gmdate( 'Ymd-His' );
wp_mkdir_p( $backup_dir );

foreach ( $locations as $id => $loc ) {
	$post = get_post( $id );
	file_put_contents( "$backup_dir/$id-post_content.html", $post->post_content );
	file_put_contents( "$backup_dir/$id-elementor.json", (string) get_post_meta( $id, '_elementor_data', true ) );

	$hero_url = "$base/wp-content/uploads/{$loc['image']}";
	$map_q    = rawurlencode( $loc['map'] );
	$doctors_html = '';
	foreach ( $loc['roster'] as $doctor_key ) {
		$doctor = $doctors[ $doctor_key ];
		$doctors_html .= '<article class="pvl-doctor"><img src="' . esc_url( "$base/wp-content/uploads/2025/10/{$doctor[1]}" ) . '" alt="Dr. ' . esc_attr( preg_replace( '/,.*$/', '', $doctor[0] ) ) . '"><div class="pvl-doctor-body"><h3>' . esc_html( $doctor[0] ) . '</h3><p>Medical Oncology &amp; Hematology</p><a href="' . esc_url( $doctor[2] ) . '">View Profile →</a></div></article>';
	}

	$html = '<div class="pv-location">' . $css
		. '<section class="pvl-hero" style="background-image:linear-gradient(180deg,rgba(15,10,42,.18),rgba(5,5,5,.9)),url(\'' . esc_url( $hero_url ) . '\')"><div class="pvl-wrap"><h1>' . esc_html( $loc['name'] ) . ' Location</h1><p>' . esc_html( $loc['subtitle'] ) . '</p></div></section>'
		. '<section class="pvl-summary"><div class="pvl-wrap pvl-summary-grid"><div><h2>Palo Verde Cancer Specialists — ' . esc_html( $loc['name'] ) . '</h2><p>' . esc_html( $loc['address'] ) . '</p><p><a href="tel:' . esc_attr( $loc['tel'] ) . '">' . esc_html( $loc['phone'] ) . '</a> • Monday–Friday, 8:00 AM–5:00 PM</p></div><div class="pvl-actions"><a class="pvl-btn" href="/schedule/">Schedule</a><a class="pvl-btn" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&amp;destination=' . esc_attr( $map_q ) . '">Directions</a></div></div></section>'
		. '<section class="pvl-section"><div class="pvl-wrap pvl-grid-two"><img class="pvl-office-img" src="' . esc_url( $hero_url ) . '" alt="Palo Verde ' . esc_attr( $loc['name'] ) . ' office"><div class="pvl-copy"><h2>About Our ' . esc_html( $loc['name'] ) . ' Location</h2><p>Our ' . esc_html( $loc['name'] ) . ' facility provides compassionate, personalized cancer care in a welcoming environment. Our physicians coordinate oncology and hematology services around each patient’s needs.</p><ul><li>Experienced oncology specialists</li><li>Personalized treatment planning</li><li>Comfortable patient environment</li><li>Convenient Valley location</li></ul></div></div></section>'
		. '<section class="pvl-section alt"><div class="pvl-wrap"><h2 class="pvl-section-title">Physicians at ' . esc_html( $loc['name'] ) . '</h2><div class="pvl-doctors">' . $doctors_html . '</div></div></section>'
		. '<section class="pvl-section"><div class="pvl-wrap"><h2 class="pvl-section-title">Services at This Location</h2><div class="pvl-services"><article class="pvl-service"><h3>Medical Oncology</h3><p>Coordinated treatment plans including chemotherapy and targeted therapies.</p></article><article class="pvl-service"><h3>Hematology</h3><p>Evaluation and management of blood disorders and related conditions.</p></article><article class="pvl-service"><h3>Immunotherapy</h3><p>Treatments designed to help the immune system recognize and fight cancer.</p></article><article class="pvl-service"><h3>Coordinated Care</h3><p>Support from diagnosis through treatment and follow-up.</p></article></div></div></section>'
		. '<section class="pvl-section alt pvl-map"><div class="pvl-wrap"><h2 class="pvl-section-title">Find Us Here</h2><iframe title="Map to Palo Verde ' . esc_attr( $loc['name'] ) . ' location" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=' . esc_attr( $map_q ) . '&amp;output=embed&amp;z=15"></iframe></div></section>'
		. '<section class="pvl-section"><div class="pvl-wrap"><div class="pvl-contact"><div><h2>Schedule Your Appointment</h2><p>Call the ' . esc_html( $loc['name'] ) . ' office or request an appointment online.</p><p class="pvl-note">Please contact the office directly to verify insurance coverage and appointment availability.</p></div><div class="pvl-contact-actions"><a class="pvl-btn" href="tel:' . esc_attr( $loc['tel'] ) . '">Call ' . esc_html( $loc['phone'] ) . '</a><a class="pvl-btn" href="/schedule/">Request Online</a></div></div></div></section></div>';

	$elementor = wp_json_encode( array( array(
		'id' => 'pvl' . $id,
		'elType' => 'container',
		'settings' => array( 'content_width' => 'full', 'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ) ),
		'elements' => array( array( 'id' => 'pvh' . $id, 'elType' => 'widget', 'settings' => array( 'html' => $html ), 'elements' => array(), 'widgetType' => 'html' ) ),
		'isInner' => false,
	) ) );

	wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $html ) ) );
	update_post_meta( $id, '_elementor_data', wp_slash( $elementor ) );
	clean_post_cache( $id );
	WP_CLI::line( "Rebuilt $id {$loc['name']}" );
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();
WP_CLI::success( 'Location rebuild complete. Backup: ' . $backup_dir );
