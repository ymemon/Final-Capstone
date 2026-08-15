<?php
/**
 * Plugin Name: AZWebCorp Free SEO Audit
 * Description: Visitor-facing SEO audit. Fetches the submitted site, measures what is actually there, and reports it. No estimated or invented metrics.
 * Version: 1.0.0
 * Author: AZWebCorp
 *
 * ---------------------------------------------------------------------------
 * EVERY NUMBER THIS TOOL SHOWS IS MEASURED.
 *
 * Each check below either observes something in the fetched response or reports
 * that it could not. There is no scoring curve, no "estimated authority", no
 * traffic guess. That constraint is the product: a visitor can verify any claim
 * here by viewing source, and an agency that shows a fabricated Domain
 * Authority to sell an audit has told the prospect something false in the first
 * thirty seconds of the relationship.
 *
 * Backlinks, referring domains, keyword rankings and traffic estimates are
 * deliberately absent. That data exists only inside Ahrefs, Semrush and Moz, and
 * it cannot be derived from a page fetch. The panel for it is wired in
 * azwc_audit_authority() and returns "unavailable" until an API key exists.
 * Do not fill it with a heuristic.
 *
 * The overall score is a weighted pass rate over the checks that ran — that is
 * stated on the page, and the per-check results are all shown, so the visitor
 * can see exactly what produced the number.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AZWC_AUDIT_VERSION', '1.0.0' );
define( 'AZWC_AUDIT_CACHE_HOURS', 6 );
define( 'AZWC_AUDIT_RATE_PER_HOUR', 8 );
define( 'AZWC_AUDIT_TIMEOUT', 20 );
define( 'AZWC_AUDIT_MAX_BYTES', 3145728 ); // 3 MB is far past any honest page.

/**
 * PageSpeed Insights works without a key at low volume but is aggressively
 * throttled. Define AZWC_PSI_KEY in wp-config.php (free from the Google Cloud
 * console) to get reliable results.
 */
function azwc_audit_psi_key() {
	if ( defined( 'AZWC_PSI_KEY' ) && AZWC_PSI_KEY ) {
		return AZWC_PSI_KEY;
	}
	return (string) get_option( 'azwc_audit_psi_key', '' );
}


/* -------------------------------------------------------------------------
 * Input safety
 * ---------------------------------------------------------------------- */

/**
 * Normalise whatever the visitor typed into a URL we are willing to fetch.
 *
 * This is the security boundary. The server fetches a URL chosen by an
 * anonymous visitor, which is a server-side request forgery primitive unless
 * private address space is refused: without the check below, someone submits
 * http://169.254.169.254/ and the audit politely prints the host's cloud
 * credentials back to them.
 */
function azwc_audit_normalize( $input ) {
	$input = trim( (string) $input );
	if ( '' === $input || strlen( $input ) > 255 ) {
		return new WP_Error( 'azwc_bad_input', 'Enter a domain, for example example.com' );
	}

	if ( ! preg_match( '#^https?://#i', $input ) ) {
		$input = 'https://' . $input;
	}

	$parts = wp_parse_url( $input );
	if ( empty( $parts['host'] ) ) {
		return new WP_Error( 'azwc_bad_input', 'That does not look like a domain.' );
	}

	$scheme = strtolower( $parts['scheme'] ?? 'https' );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return new WP_Error( 'azwc_bad_input', 'Only http and https addresses can be checked.' );
	}

	$host = strtolower( $parts['host'] );

	// A hostname with no dot is a local name, not a public site.
	if ( false === strpos( $host, '.' ) ) {
		return new WP_Error( 'azwc_bad_input', 'Enter a full domain, for example example.com' );
	}

	$ips = azwc_audit_resolve( $host );
	if ( is_wp_error( $ips ) ) {
		return $ips;
	}
	foreach ( $ips as $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return new WP_Error( 'azwc_private', 'That address is on a private network and cannot be checked.' );
		}
	}

	$path = $parts['path'] ?? '/';
	return $scheme . '://' . $host . ( '' === $path ? '/' : $path );
}

function azwc_audit_resolve( $host ) {
	$records = @dns_get_record( $host, DNS_A | DNS_AAAA );
	$ips     = array();
	if ( $records ) {
		foreach ( $records as $r ) {
			if ( ! empty( $r['ip'] ) ) {
				$ips[] = $r['ip'];
			}
			if ( ! empty( $r['ipv6'] ) ) {
				$ips[] = $r['ipv6'];
			}
		}
	}
	if ( ! $ips ) {
		$resolved = gethostbyname( $host );
		if ( $resolved && $resolved !== $host ) {
			$ips[] = $resolved;
		}
	}
	if ( ! $ips ) {
		return new WP_Error( 'azwc_dns', 'That domain does not resolve. Check the spelling.' );
	}
	return $ips;
}

/** Simple per-IP throttle. The tool makes outbound requests on demand. */
function azwc_audit_rate_ok() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'azwc_audit_rate_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= AZWC_AUDIT_RATE_PER_HOUR ) {
		return false;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return true;
}


/* -------------------------------------------------------------------------
 * Fetching
 * ---------------------------------------------------------------------- */

function azwc_audit_fetch( $url, $method = 'GET' ) {
	$started = microtime( true );
	$args    = array(
		'timeout'             => AZWC_AUDIT_TIMEOUT,
		'redirection'         => 5,
		'method'              => $method,
		'user-agent'          => 'AZWebCorpSEOAudit/' . AZWC_AUDIT_VERSION . ' (+https://azwebcorp.com/free-seo-audit/)',
		'limit_response_size' => AZWC_AUDIT_MAX_BYTES,
		'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml,*/*' ),
	);

	$response = wp_remote_request( $url, $args );
	$elapsed  = ( microtime( true ) - $started ) * 1000;

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return array(
		'status'  => (int) wp_remote_retrieve_response_code( $response ),
		'headers' => wp_remote_retrieve_headers( $response )->getAll(),
		'body'    => (string) wp_remote_retrieve_body( $response ),
		'ms'      => (int) round( $elapsed ),
	);
}

/**
 * Follow the redirect chain by hand.
 *
 * wp_remote_request follows redirects silently and reports only the final
 * response, but the chain itself is the finding: an http -> https -> www ->
 * trailing-slash sequence is four round trips before a byte of content, and it
 * is invisible unless each hop is requested individually.
 */
function azwc_audit_chain( $url, $max = 5 ) {
	$chain   = array();
	$current = $url;

	for ( $i = 0; $i < $max; $i++ ) {
		$r = wp_remote_request( $current, array(
			'timeout'     => AZWC_AUDIT_TIMEOUT,
			'redirection' => 0,
			'method'      => 'HEAD',
			'user-agent'  => 'AZWebCorpSEOAudit/' . AZWC_AUDIT_VERSION,
		) );
		if ( is_wp_error( $r ) ) {
			break;
		}
		$code     = (int) wp_remote_retrieve_response_code( $r );
		$location = wp_remote_retrieve_header( $r, 'location' );
		$chain[]  = array( 'url' => $current, 'status' => $code );

		if ( $code < 300 || $code >= 400 || ! $location ) {
			break;
		}
		$current = 0 === strpos( $location, 'http' ) ? $location : untrailingslashit( $current ) . '/' . ltrim( $location, '/' );
	}

	return $chain;
}


/* -------------------------------------------------------------------------
 * Checks
 *
 * Every check returns: id, label, status (pass|warn|fail|info), weight, and a
 * detail string describing what was actually observed. "info" carries no weight
 * — it reports something true that is not a pass or a failure.
 * ---------------------------------------------------------------------- */

function azwc_audit_check( $id, $label, $status, $detail, $weight = 1, $group = 'technical' ) {
	return compact( 'id', 'label', 'status', 'detail', 'weight', 'group' );
}

function azwc_audit_text( $html ) {
	$html = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html );
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
}

function azwc_audit_run_checks( $url, $page, $chain ) {
	$html    = $page['body'];
	$headers = array_change_key_case( $page['headers'] );
	$checks  = array();
	$parts   = wp_parse_url( $url );
	$host    = $parts['host'];

	/* --- indexability ---------------------------------------------------- */

	$robots_meta = '';
	if ( preg_match( '#<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']+)#i', $html, $m ) ) {
		$robots_meta = strtolower( $m[1] );
	}
	$x_robots  = strtolower( is_array( $headers['x-robots-tag'] ?? '' ) ? implode( ',', $headers['x-robots-tag'] ) : ( $headers['x-robots-tag'] ?? '' ) );
	$noindexed = ( false !== strpos( $robots_meta, 'noindex' ) ) || ( false !== strpos( $x_robots, 'noindex' ) );

	$checks[] = azwc_audit_check(
		'indexable',
		'Search engines are allowed to index this page',
		$noindexed ? 'fail' : 'pass',
		$noindexed
			? 'A noindex directive is present, which removes this page from search results entirely. Everything else on this report is moot until it is removed.'
			: 'No noindex directive found in the meta robots tag or the X-Robots-Tag header.',
		4,
		'indexability'
	);

	$robots_txt = azwc_audit_fetch( $parts['scheme'] . '://' . $host . '/robots.txt' );
	$has_robots = ! is_wp_error( $robots_txt ) && 200 === $robots_txt['status'];
	$rt_body    = $has_robots ? $robots_txt['body'] : '';
	$blocks_all = (bool) preg_match( '/^\s*disallow:\s*\/\s*$/im', $rt_body );

	$checks[] = azwc_audit_check(
		'robots_txt',
		'robots.txt is present and not blocking the site',
		$blocks_all ? 'fail' : ( $has_robots ? 'pass' : 'warn' ),
		$blocks_all
			? 'robots.txt contains "Disallow: /", which asks every crawler to skip the entire site.'
			: ( $has_robots
				? 'robots.txt found and it does not block crawling.'
				: 'No robots.txt found. Not an error — crawlers assume full access — but it is where the sitemap should be declared.' ),
		2,
		'indexability'
	);

	$sitemap_in_robots = (bool) preg_match( '/^\s*sitemap:\s*(\S+)/im', $rt_body, $sm );
	$sitemap_url       = $sitemap_in_robots ? trim( $sm[1] ) : $parts['scheme'] . '://' . $host . '/sitemap.xml';
	$sitemap           = azwc_audit_fetch( $sitemap_url );
	$has_sitemap       = ! is_wp_error( $sitemap ) && 200 === $sitemap['status'] && false !== stripos( $sitemap['body'], '<' );
	$sitemap_urls      = $has_sitemap ? substr_count( $sitemap['body'], '<loc>' ) : 0;

	$checks[] = azwc_audit_check(
		'sitemap',
		'An XML sitemap is reachable',
		$has_sitemap ? 'pass' : 'warn',
		$has_sitemap
			? sprintf( 'Sitemap found at %s listing %d URL%s.', esc_url_raw( $sitemap_url ), $sitemap_urls, 1 === $sitemap_urls ? '' : 's' )
			: 'No XML sitemap found at /sitemap.xml or declared in robots.txt. Crawlers will still find pages through links, but a sitemap makes discovery faster and more complete.',
		2,
		'indexability'
	);

	$canonical = '';
	if ( preg_match( '#<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)#i', $html, $m ) ) {
		$canonical = trim( $m[1] );
	}
	$checks[] = azwc_audit_check(
		'canonical',
		'A canonical URL is declared',
		$canonical ? 'pass' : 'warn',
		$canonical
			? 'Canonical points to ' . esc_html( $canonical )
			: 'No canonical tag. Without one, the same page reached through different URLs — with and without www, with tracking parameters — can be treated as separate duplicate pages.',
		2,
		'indexability'
	);

	/* --- transport ------------------------------------------------------- */

	$is_https = 'https' === $parts['scheme'];
	$checks[] = azwc_audit_check(
		'https',
		'The site is served over HTTPS',
		$is_https ? 'pass' : 'fail',
		$is_https
			? 'HTTPS is in use and the certificate validated during this request.'
			: 'This site was reached over plain HTTP. Browsers mark it "Not secure", and HTTPS is a confirmed ranking signal.',
		3,
		'technical'
	);

	$hops     = max( 0, count( $chain ) - 1 );
	$checks[] = azwc_audit_check(
		'redirects',
		'Redirects are kept short',
		$hops <= 1 ? 'pass' : ( $hops <= 2 ? 'warn' : 'fail' ),
		0 === $hops
			? 'The URL resolves directly with no redirect.'
			: sprintf(
				'%d redirect%s before the page loads: %s',
				$hops,
				1 === $hops ? '' : 's',
				esc_html( implode( ' -> ', wp_list_pluck( $chain, 'url' ) ) )
			),
		2,
		'technical'
	);

	$mixed = 0;
	if ( $is_https && preg_match_all( '#(?:src|href)=["\']http://[^"\']+#i', $html, $mm ) ) {
		$mixed = count( $mm[0] );
	}
	$checks[] = azwc_audit_check(
		'mixed_content',
		'No insecure resources on a secure page',
		$mixed > 0 ? 'fail' : 'pass',
		$mixed > 0
			? sprintf( '%d resource%s referenced over plain http on an https page. Browsers block or warn on these.', $mixed, 1 === $mixed ? ' is' : 's are' )
			: 'All referenced resources use https.',
		2,
		'technical'
	);

	$compressed = ! empty( $headers['content-encoding'] );
	$checks[]   = azwc_audit_check(
		'compression',
		'The HTML is compressed in transit',
		$compressed ? 'pass' : 'warn',
		$compressed
			? 'Content-Encoding: ' . esc_html( is_array( $headers['content-encoding'] ) ? implode( ',', $headers['content-encoding'] ) : $headers['content-encoding'] )
			: 'No Content-Encoding header. Enabling gzip or brotli typically cuts HTML transfer size by 60-80%.',
		1,
		'technical'
	);

	$bytes    = strlen( $html );
	$checks[] = azwc_audit_check(
		'html_size',
		'HTML document size is reasonable',
		$bytes < 150000 ? 'pass' : ( $bytes < 400000 ? 'warn' : 'fail' ),
		sprintf( 'The HTML document is %s. Measured server response time was %d ms.', size_format( $bytes ), $page['ms'] ),
		1,
		'technical'
	);

	/* --- on-page --------------------------------------------------------- */

	$title = '';
	if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
		$title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ) ) );
	}
	$tlen     = mb_strlen( $title );
	$checks[] = azwc_audit_check(
		'title',
		'The page has a title of usable length',
		'' === $title ? 'fail' : ( ( $tlen >= 20 && $tlen <= 60 ) ? 'pass' : 'warn' ),
		'' === $title
			? 'No title tag. This is the headline of your search result — without it Google invents one from the page content.'
			: sprintf( '%d characters: "%s"%s', $tlen, esc_html( $title ), $tlen > 60 ? ' — likely truncated in results beyond about 60.' : ( $tlen < 20 ? ' — short enough that it is probably not describing the page fully.' : '' ) ),
		3,
		'onpage'
	);

	$desc = '';
	if ( preg_match( '#<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)#i', $html, $m ) ) {
		$desc = trim( html_entity_decode( $m[1] ) );
	}
	$dlen     = mb_strlen( $desc );
	$checks[] = azwc_audit_check(
		'description',
		'A meta description is present',
		'' === $desc ? 'fail' : ( ( $dlen >= 70 && $dlen <= 165 ) ? 'pass' : 'warn' ),
		'' === $desc
			? 'No meta description. Google will pull an arbitrary sentence from the page instead, which rarely reads like an invitation to click.'
			: sprintf( '%d characters%s', $dlen, $dlen > 165 ? ' — will be cut off in results.' : ( $dlen < 70 ? ' — shorter than the space available.' : '.' ) ),
		2,
		'onpage'
	);

	preg_match_all( '#<h1[^>]*>(.*?)</h1>#is', $html, $h1s );
	$h1_count = count( $h1s[0] );
	$checks[] = azwc_audit_check(
		'h1',
		'Exactly one H1 heading',
		1 === $h1_count ? 'pass' : ( 0 === $h1_count ? 'fail' : 'warn' ),
		0 === $h1_count
			? 'No H1 found. The H1 is the clearest statement of what a page is about.'
			: ( 1 === $h1_count
				? 'One H1: "' . esc_html( trim( wp_strip_all_tags( $h1s[1][0] ) ) ) . '"'
				: $h1_count . ' H1 tags. Multiple top-level headings dilute the signal about the page subject.' ),
		2,
		'onpage'
	);

	$words    = str_word_count( azwc_audit_text( $html ) );
	$checks[] = azwc_audit_check(
		'content_depth',
		'The page has substantive content',
		$words >= 300 ? 'pass' : ( $words >= 120 ? 'warn' : 'fail' ),
		sprintf(
			'%d words of visible text.%s',
			$words,
			$words < 300 ? ' Pages this thin rarely rank for competitive terms, because there is not enough on them to establish relevance.' : ''
		),
		2,
		'onpage'
	);

	preg_match_all( '#<img\b[^>]*>#i', $html, $imgs );
	$img_total = count( $imgs[0] );
	$img_noalt = 0;
	foreach ( $imgs[0] as $img ) {
		if ( ! preg_match( '#\balt\s*=\s*["\'][^"\']*[^\s"\']#i', $img ) ) {
			$img_noalt++;
		}
	}
	$checks[] = azwc_audit_check(
		'image_alt',
		'Images carry alt text',
		0 === $img_total ? 'info' : ( 0 === $img_noalt ? 'pass' : ( $img_noalt / $img_total < 0.25 ? 'warn' : 'fail' ) ),
		0 === $img_total
			? 'No images found on this page.'
			: sprintf( '%d of %d images have no alt text. Alt text is what a screen reader announces and what Google reads to understand an image.', $img_noalt, $img_total ),
		1,
		'onpage'
	);

	$viewport = (bool) preg_match( '#<meta[^>]+name=["\']viewport["\']#i', $html );
	$checks[] = azwc_audit_check(
		'viewport',
		'A mobile viewport is declared',
		$viewport ? 'pass' : 'fail',
		$viewport
			? 'A viewport meta tag is present, so the page adapts to phone screens.'
			: 'No viewport meta tag. Phones will render the page at desktop width and zoom out — Google indexes the mobile version first.',
		3,
		'onpage'
	);

	$lang     = (bool) preg_match( '#<html[^>]+lang=["\'][a-z]#i', $html );
	$checks[] = azwc_audit_check(
		'lang',
		'The page declares its language',
		$lang ? 'pass' : 'warn',
		$lang ? 'A lang attribute is set on the html element.' : 'No lang attribute on the html element.',
		1,
		'onpage'
	);

	/* --- structured data and social -------------------------------------- */

	preg_match_all( '#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $ld );
	$types = array();
	foreach ( $ld[1] as $block ) {
		$data = json_decode( trim( $block ), true );
		if ( ! is_array( $data ) ) {
			continue;
		}
		array_walk_recursive( $data, function ( $v, $k ) use ( &$types ) {
			if ( '@type' === $k && is_string( $v ) ) {
				$types[ $v ] = true;
			}
		} );
	}
	$types    = array_keys( $types );
	$checks[] = azwc_audit_check(
		'structured_data',
		'Structured data is present',
		$types ? 'pass' : 'warn',
		$types
			? 'JSON-LD found describing: ' . esc_html( implode( ', ', array_slice( $types, 0, 8 ) ) )
			: 'No JSON-LD structured data. This is what produces star ratings, FAQ dropdowns and business details in search results.',
		2,
		'structured'
	);

	$og       = (bool) preg_match( '#<meta[^>]+property=["\']og:title["\']#i', $html );
	$og_img   = (bool) preg_match( '#<meta[^>]+property=["\']og:image["\']#i', $html );
	$checks[] = azwc_audit_check(
		'open_graph',
		'Social sharing tags are set',
		( $og && $og_img ) ? 'pass' : ( $og ? 'warn' : 'fail' ),
		( $og && $og_img )
			? 'Open Graph title and image are both present, so shared links render as a card.'
			: ( $og
				? 'og:title is set but og:image is missing — shared links will have no thumbnail.'
				: 'No Open Graph tags. Links shared to Facebook, LinkedIn or WhatsApp will show a bare URL.' ),
		1,
		'structured'
	);

	$favicon  = (bool) preg_match( '#<link[^>]+rel=["\'][^"\']*icon#i', $html );
	$checks[] = azwc_audit_check(
		'favicon',
		'A favicon is declared',
		$favicon ? 'pass' : 'warn',
		$favicon ? 'A favicon link is present.' : 'No favicon declared. It appears beside your result on mobile search.',
		1,
		'structured'
	);

	/* --- links ----------------------------------------------------------- */

	preg_match_all( '#<a\b[^>]+href=["\']([^"\'#]+)#i', $html, $links );
	$internal = 0;
	$external = 0;
	foreach ( $links[1] as $href ) {
		if ( 0 === strpos( $href, '/' ) || false !== stripos( $href, $host ) ) {
			$internal++;
		} elseif ( preg_match( '#^https?://#i', $href ) ) {
			$external++;
		}
	}
	$checks[] = azwc_audit_check(
		'links',
		'The page links onward into the site',
		$internal >= 5 ? 'pass' : ( $internal >= 1 ? 'warn' : 'fail' ),
		sprintf(
			'%d internal and %d external links. Internal links are how crawlers find the rest of the site and how ranking strength moves between pages.',
			$internal,
			$external
		),
		1,
		'onpage'
	);

	return $checks;
}


/* -------------------------------------------------------------------------
 * PageSpeed Insights — real field and lab data from Google
 * ---------------------------------------------------------------------- */

function azwc_audit_psi( $url, $strategy = 'mobile' ) {
	$endpoint = add_query_arg(
		array_filter( array(
			'url'      => rawurlencode( $url ),
			'strategy' => $strategy,
			'category' => 'performance',
			'key'      => azwc_audit_psi_key(),
		) ),
		'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
	);

	$r = wp_remote_get( $endpoint, array( 'timeout' => 45 ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $data ) || empty( $data['lighthouseResult'] ) ) {
		return null;
	}

	$audits = $data['lighthouseResult']['audits'] ?? array();
	$score  = $data['lighthouseResult']['categories']['performance']['score'] ?? null;

	$metric = function ( $id ) use ( $audits ) {
		if ( empty( $audits[ $id ] ) ) {
			return null;
		}
		return array(
			'display' => $audits[ $id ]['displayValue'] ?? null,
			'value'   => $audits[ $id ]['numericValue'] ?? null,
			'score'   => $audits[ $id ]['score'] ?? null,
		);
	};

	// Field data is what real visitors experienced; lab data is a simulation.
	// Both are reported, labelled, and never blended into one number.
	$field = array();
	if ( ! empty( $data['loadingExperience']['metrics'] ) ) {
		foreach ( $data['loadingExperience']['metrics'] as $key => $m ) {
			$field[ $key ] = array(
				'percentile' => $m['percentile'] ?? null,
				'category'   => $m['category'] ?? null,
			);
		}
	}

	return array(
		'strategy' => $strategy,
		'score'    => null === $score ? null : (int) round( $score * 100 ),
		'lab'      => array(
			'lcp' => $metric( 'largest-contentful-paint' ),
			'cls' => $metric( 'cumulative-layout-shift' ),
			'tbt' => $metric( 'total-blocking-time' ),
			'fcp' => $metric( 'first-contentful-paint' ),
			'si'  => $metric( 'speed-index' ),
		),
		'field'    => $field,
	);
}


/* -------------------------------------------------------------------------
 * Authority data — deliberately empty
 * ---------------------------------------------------------------------- */

/**
 * Backlinks, referring domains, keyword counts and traffic estimates.
 *
 * These cannot be measured by fetching a page. They come from a crawler index
 * that costs money to maintain, which is why every tool showing them for free
 * is either reselling an API or inventing the numbers. This returns
 * "unavailable" and the front end says so plainly.
 *
 * To enable: add an Ahrefs or Semrush call here and return the real figures.
 * Do not approximate it from anything observable on the page — a plausible
 * fabrication is worse than an honest gap, because the visitor cannot tell.
 */
function azwc_audit_authority( $url ) {
	return array(
		'available' => false,
		'reason'    => 'Backlink and keyword data comes from a paid crawler index. We do not estimate it, so this section stays empty rather than showing you a number we made up.',
	);
}


/* -------------------------------------------------------------------------
 * Scoring
 * ---------------------------------------------------------------------- */

function azwc_audit_score( $checks ) {
	$groups = array();
	$earned = 0;
	$total  = 0;

	foreach ( $checks as $c ) {
		if ( 'info' === $c['status'] ) {
			continue;
		}
		$points = 'pass' === $c['status'] ? 1.0 : ( 'warn' === $c['status'] ? 0.5 : 0.0 );
		$earned += $points * $c['weight'];
		$total  += $c['weight'];

		$g = $c['group'];
		if ( ! isset( $groups[ $g ] ) ) {
			$groups[ $g ] = array( 'earned' => 0, 'total' => 0 );
		}
		$groups[ $g ]['earned'] += $points * $c['weight'];
		$groups[ $g ]['total']  += $c['weight'];
	}

	$out = array();
	foreach ( $groups as $g => $v ) {
		$out[ $g ] = $v['total'] > 0 ? (int) round( $v['earned'] / $v['total'] * 100 ) : null;
	}

	return array(
		'overall' => $total > 0 ? (int) round( $earned / $total * 100 ) : null,
		'groups'  => $out,
	);
}


/* -------------------------------------------------------------------------
 * REST endpoint
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'azwc/v1', '/audit', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'domain' => array( 'required' => true, 'type' => 'string' ),
		),
		'callback'            => 'azwc_audit_rest',
	) );
} );

function azwc_audit_rest( WP_REST_Request $request ) {
	$url = azwc_audit_normalize( $request->get_param( 'domain' ) );
	if ( is_wp_error( $url ) ) {
		return new WP_REST_Response( array( 'error' => $url->get_error_message() ), 400 );
	}

	$cache_key = 'azwc_audit_' . md5( $url );
	$cached    = get_transient( $cache_key );
	if ( $cached ) {
		$cached['cached'] = true;
		return new WP_REST_Response( $cached, 200 );
	}

	if ( ! azwc_audit_rate_ok() ) {
		return new WP_REST_Response(
			array( 'error' => 'That is a lot of audits from one place. Try again in an hour, or call us and we will run it for you.' ),
			429
		);
	}

	$page = azwc_audit_fetch( $url );
	if ( is_wp_error( $page ) ) {
		return new WP_REST_Response(
			array( 'error' => 'Could not reach that site: ' . $page->get_error_message() ),
			502
		);
	}
	if ( $page['status'] >= 400 ) {
		return new WP_REST_Response(
			array( 'error' => sprintf( 'That URL returned HTTP %d, so there is no page to analyse.', $page['status'] ) ),
			502
		);
	}

	$chain  = azwc_audit_chain( $url );
	$checks = azwc_audit_run_checks( $url, $page, $chain );
	$score  = azwc_audit_score( $checks );

	$result = array(
		'url'       => $url,
		'fetched'   => gmdate( 'c' ),
		'status'    => $page['status'],
		'ms'        => $page['ms'],
		'score'     => $score,
		'checks'    => $checks,
		'psi'       => array(
			'mobile'  => azwc_audit_psi( $url, 'mobile' ),
			'desktop' => azwc_audit_psi( $url, 'desktop' ),
		),
		'authority' => azwc_audit_authority( $url ),
		'cached'    => false,
	);

	set_transient( $cache_key, $result, AZWC_AUDIT_CACHE_HOURS * HOUR_IN_SECONDS );

	return new WP_REST_Response( $result, 200 );
}


/* -------------------------------------------------------------------------
 * Front end
 * ---------------------------------------------------------------------- */

add_shortcode( 'azwc_seo_audit', 'azwc_audit_shortcode' );

function azwc_audit_shortcode() {
	ob_start();
	?>
	<div id="azwc-audit" data-endpoint="<?php echo esc_url( rest_url( 'azwc/v1/audit' ) ); ?>">
		<form class="azwc-audit-form" novalidate>
			<label for="azwc-audit-domain">Enter your website address</label>
			<div class="azwc-audit-row">
				<input id="azwc-audit-domain" name="domain" type="text" inputmode="url" autocomplete="url"
				       placeholder="yourbusiness.com" required>
				<button type="submit">Run the audit</button>
			</div>
			<p class="azwc-audit-note">Takes about 30 seconds. Nothing is stored and no email is required.</p>
		</form>

		<div class="azwc-audit-status" role="status" aria-live="polite" hidden></div>
		<div class="azwc-audit-results" hidden></div>
	</div>
	<?php
	azwc_audit_styles();
	azwc_audit_script();
	return ob_get_clean();
}

function azwc_audit_styles() {
	?>
	<style id="azwc-audit-css">
	#azwc-audit{--ink:#111827;--muted:#6b7280;--line:#e5e7eb;--pass:#0f9d58;--warn:#e8a33d;--fail:#d64545;--accent:#f4c430;--card:#fff;--bg:#f7f8fa;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);line-height:1.6;max-width:1080px;margin:0 auto}
	#azwc-audit *{box-sizing:border-box}
	#azwc-audit .azwc-audit-form{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:28px}
	#azwc-audit label{display:block;font-weight:700;font-size:14px;margin-bottom:10px}
	#azwc-audit .azwc-audit-row{display:flex;gap:10px;flex-wrap:wrap}
	#azwc-audit input{flex:1 1 260px;min-height:52px;padding:0 16px;font-size:16px;border:1px solid #cbd2da;border-radius:10px;color:var(--ink)}
	#azwc-audit input:focus{outline:2px solid var(--accent);outline-offset:1px;border-color:#b9a24a}
	#azwc-audit button{min-height:52px;padding:0 26px;font-size:15px;font-weight:800;color:#12203a;background:var(--accent);border:0;border-radius:10px;cursor:pointer}
	#azwc-audit button:hover{background:#ffd857}
	#azwc-audit button[disabled]{opacity:.55;cursor:progress}
	#azwc-audit .azwc-audit-note{margin:12px 0 0;color:var(--muted);font-size:13px}
	#azwc-audit .azwc-audit-status{margin-top:18px;padding:16px 18px;background:#fffbe9;border:1px solid #f0e0a8;border-radius:10px;font-size:14px}
	#azwc-audit .azwc-audit-status.error{background:#fdf0f0;border-color:#f2c9c9;color:#8a2b2b}
	#azwc-audit .azwc-panel{margin-top:22px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:26px}
	#azwc-audit h3{margin:0 0 4px;font-size:19px;letter-spacing:-.01em}
	#azwc-audit .azwc-sub{margin:0 0 20px;color:var(--muted);font-size:13px}
	#azwc-audit .azwc-top{display:grid;grid-template-columns:200px 1fr;gap:32px;align-items:center}
	#azwc-audit .azwc-gauge{text-align:center}
	#azwc-audit .azwc-gauge figcaption{margin-top:6px;font-size:12px;color:var(--muted)}
	#azwc-audit .azwc-bars{display:grid;gap:13px}
	#azwc-audit .azwc-bar-row{display:grid;grid-template-columns:130px 1fr 44px;gap:12px;align-items:center;font-size:13px}
	#azwc-audit .azwc-track{height:9px;background:#eceff3;border-radius:99px;overflow:hidden}
	#azwc-audit .azwc-fill{height:100%;border-radius:99px}
	#azwc-audit .azwc-bar-val{text-align:right;font-weight:800;font-variant-numeric:tabular-nums}
	#azwc-audit .azwc-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
	#azwc-audit .azwc-metric{padding:16px;background:var(--bg);border:1px solid var(--line);border-radius:11px}
	#azwc-audit .azwc-metric b{display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
	#azwc-audit .azwc-metric strong{display:block;margin-top:7px;font-size:25px;font-weight:750;letter-spacing:-.02em}
	#azwc-audit .azwc-metric span{font-size:11px;color:var(--muted)}
	#azwc-audit .azwc-checks{display:grid;gap:2px}
	#azwc-audit .azwc-check{display:grid;grid-template-columns:22px 1fr;gap:12px;padding:15px 0;border-bottom:1px solid var(--line)}
	#azwc-audit .azwc-check:last-child{border-bottom:0}
	#azwc-audit .azwc-dot{width:16px;height:16px;margin-top:4px;border-radius:50%}
	#azwc-audit .azwc-check h4{margin:0 0 3px;font-size:14.5px;font-weight:750}
	#azwc-audit .azwc-check p{margin:0;color:var(--muted);font-size:13px;word-break:break-word}
	#azwc-audit .azwc-legend{display:flex;flex-wrap:wrap;gap:16px;margin-bottom:18px;font-size:12px;color:var(--muted)}
	#azwc-audit .azwc-legend i{display:inline-block;width:10px;height:10px;margin-right:6px;border-radius:50%;vertical-align:-1px}
	#azwc-audit .azwc-empty{padding:18px;background:var(--bg);border:1px dashed #cfd6de;border-radius:11px;color:var(--muted);font-size:13.5px}
	#azwc-audit .azwc-cta{margin-top:22px;padding:26px;background:#0b192e;border-radius:14px;color:#fff}
	#azwc-audit .azwc-cta h3{color:#fff}
	#azwc-audit .azwc-cta p{margin:8px 0 16px;color:#c2cad7;font-size:14px}
	#azwc-audit .azwc-cta a{display:inline-flex;align-items:center;min-height:46px;padding:0 22px;background:var(--accent);color:#12203a;border-radius:9px;font-weight:800;font-size:14px;text-decoration:none}
	@media(max-width:640px){#azwc-audit .azwc-top{grid-template-columns:1fr}#azwc-audit .azwc-bar-row{grid-template-columns:96px 1fr 40px}}
	</style>
	<?php
}

function azwc_audit_script() {
	?>
	<script id="azwc-audit-js">
	(function () {
		var root = document.getElementById('azwc-audit');
		if (!root) { return; }
		var form = root.querySelector('.azwc-audit-form');
		var input = root.querySelector('#azwc-audit-domain');
		var button = form.querySelector('button');
		var statusEl = root.querySelector('.azwc-audit-status');
		var out = root.querySelector('.azwc-audit-results');

		var COLOR = { pass: '#0f9d58', warn: '#e8a33d', fail: '#d64545', info: '#9aa3ad' };
		var GROUPS = {
			indexability: 'Indexability',
			technical: 'Technical',
			onpage: 'On-page',
			structured: 'Structured data'
		};

		function esc(s) {
			return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		function scoreColor(n) {
			if (n === null || n === undefined) { return '#9aa3ad'; }
			return n >= 80 ? COLOR.pass : (n >= 50 ? COLOR.warn : COLOR.fail);
		}

		/* An SVG ring rather than an image: it scales, it prints, and there is no
		   external request for a page that is meant to demonstrate performance. */
		function gauge(score) {
			var r = 62, c = 2 * Math.PI * r;
			var pct = (score === null || score === undefined) ? 0 : Math.max(0, Math.min(100, score));
			var dash = (pct / 100) * c;
			return '<figure class="azwc-gauge"><svg viewBox="0 0 160 160" width="170" height="170" role="img" aria-label="Overall score ' + pct + ' out of 100">'
				+ '<circle cx="80" cy="80" r="' + r + '" fill="none" stroke="#eceff3" stroke-width="13"/>'
				+ '<circle cx="80" cy="80" r="' + r + '" fill="none" stroke="' + scoreColor(score) + '" stroke-width="13"'
				+ ' stroke-linecap="round" stroke-dasharray="' + dash.toFixed(1) + ' ' + c.toFixed(1) + '"'
				+ ' transform="rotate(-90 80 80)"/>'
				+ '<text x="80" y="88" text-anchor="middle" font-size="40" font-weight="700" fill="#111827">'
				+ (score === null || score === undefined ? '—' : pct) + '</text>'
				+ '</svg><figcaption>Weighted pass rate across ' + '</figcaption></figure>';
		}

		function bars(groups) {
			var rows = Object.keys(GROUPS).map(function (key) {
				var v = groups[key];
				if (v === undefined || v === null) { return ''; }
				return '<div class="azwc-bar-row"><span>' + GROUPS[key] + '</span>'
					+ '<span class="azwc-track"><span class="azwc-fill" style="width:' + v + '%;background:' + scoreColor(v) + '"></span></span>'
					+ '<span class="azwc-bar-val">' + v + '</span></div>';
			}).join('');
			return '<div class="azwc-bars">' + rows + '</div>';
		}

		function psiPanel(psi) {
			var have = ['mobile', 'desktop'].filter(function (k) { return psi && psi[k]; });
			if (!have.length) {
				return '<section class="azwc-panel"><h3>Speed</h3>'
					+ '<p class="azwc-sub">Google PageSpeed Insights</p>'
					+ '<div class="azwc-empty">Google\'s PageSpeed API did not return data for this URL — usually rate limiting rather than a problem with the site. Everything else on this report is unaffected.</div></section>';
			}
			var html = '<section class="azwc-panel"><h3>Speed</h3>'
				+ '<p class="azwc-sub">Measured by Google PageSpeed Insights, not by us. Field data is what real Chrome users experienced; lab data is Google\'s simulation on a throttled connection.</p>';

			have.forEach(function (k) {
				var p = psi[k];
				var m = p.lab || {};
				html += '<h4 style="margin:18px 0 10px;font-size:14px;text-transform:capitalize">' + k
					+ (p.score === null ? '' : ' — performance score ' + p.score + '/100') + '</h4>'
					+ '<div class="azwc-metrics">'
					+ metric('Largest contentful paint', m.lcp)
					+ metric('Cumulative layout shift', m.cls)
					+ metric('Total blocking time', m.tbt)
					+ metric('First contentful paint', m.fcp)
					+ '</div>';

				var f = p.field || {};
				var keys = Object.keys(f);
				if (keys.length) {
					html += '<p class="azwc-sub" style="margin:14px 0 8px">Field data from real visitors (Chrome UX Report):</p><div class="azwc-metrics">';
					keys.forEach(function (fk) {
						var label = fk.replace(/_/g, ' ').toLowerCase();
						var val = f[fk].percentile;
						var unit = /shift/i.test(fk) ? '' : ' ms';
						var shown = /shift/i.test(fk) ? (val / 100).toFixed(2) : val;
						html += '<div class="azwc-metric"><b>' + esc(label) + '</b><strong>' + esc(shown) + esc(unit)
							+ '</strong><span>' + esc((f[fk].category || '').toLowerCase().replace('average', 'needs improvement')) + '</span></div>';
					});
					html += '</div>';
				}
			});
			return html + '</section>';
		}

		function metric(label, m) {
			if (!m || (m.display === null && m.value === null)) { return ''; }
			var color = m.score === null || m.score === undefined ? '#111827' : scoreColor(m.score * 100);
			return '<div class="azwc-metric"><b>' + esc(label) + '</b>'
				+ '<strong style="color:' + color + '">' + esc(m.display || m.value) + '</strong></div>';
		}

		function checksPanel(checks) {
			var order = { fail: 0, warn: 1, pass: 2, info: 3 };
			var sorted = checks.slice().sort(function (a, b) { return order[a.status] - order[b.status]; });
			var items = sorted.map(function (c) {
				return '<div class="azwc-check"><span class="azwc-dot" style="background:' + COLOR[c.status] + '"></span>'
					+ '<div><h4>' + esc(c.label) + '</h4><p>' + esc(c.detail) + '</p></div></div>';
			}).join('');
			return '<section class="azwc-panel"><h3>What we found</h3>'
				+ '<p class="azwc-sub">Every line below was observed in the response from your server. Failures are listed first.</p>'
				+ '<div class="azwc-legend">'
				+ '<span><i style="background:' + COLOR.fail + '"></i>Needs fixing</span>'
				+ '<span><i style="background:' + COLOR.warn + '"></i>Worth improving</span>'
				+ '<span><i style="background:' + COLOR.pass + '"></i>Good</span>'
				+ '<span><i style="background:' + COLOR.info + '"></i>Note</span></div>'
				+ '<div class="azwc-checks">' + items + '</div></section>';
		}

		function authorityPanel(a) {
			if (a && a.available) { return ''; }
			return '<section class="azwc-panel"><h3>Backlinks and rankings</h3>'
				+ '<p class="azwc-sub">Not shown, on purpose</p>'
				+ '<div class="azwc-empty">' + esc(a && a.reason ? a.reason : 'Not available.') + '</div></section>';
		}

		function render(d) {
			var counts = { pass: 0, warn: 0, fail: 0 };
			d.checks.forEach(function (c) { if (counts[c.status] !== undefined) { counts[c.status]++; } });
			var scored = d.checks.filter(function (c) { return c.status !== 'info'; }).length;

			var html = '<section class="azwc-panel"><h3>' + esc(d.url) + '</h3>'
				+ '<p class="azwc-sub">Checked ' + new Date(d.fetched).toLocaleString()
				+ ' &middot; server responded in ' + esc(d.ms) + ' ms'
				+ (d.cached ? ' &middot; cached result' : '') + '</p>'
				+ '<div class="azwc-top">'
				+ gauge(d.score.overall).replace('across </figcaption>', 'across ' + scored + ' checks</figcaption>')
				+ '<div>' + bars(d.score.groups)
				+ '<p class="azwc-sub" style="margin:16px 0 0">'
				+ counts.fail + ' need fixing, ' + counts.warn + ' worth improving, ' + counts.pass + ' passing.'
				+ '</p></div></div></section>';

			html += checksPanel(d.checks);
			html += psiPanel(d.psi);
			html += authorityPanel(d.authority);
			html += '<section class="azwc-cta"><h3>Want these fixed?</h3>'
				+ '<p>We are in Gilbert. Call (480) 818-5761 or email info@azwebcorp.com and we will walk through this report with you — no charge, no obligation.</p>'
				+ '<a href="tel:+14808185761">Call (480) 818-5761</a></section>';

			out.innerHTML = html;
			out.hidden = false;
			out.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var domain = input.value.trim();
			if (!domain) { return; }

			out.hidden = true;
			statusEl.hidden = false;
			statusEl.className = 'azwc-audit-status';
			statusEl.textContent = 'Fetching ' + domain + ' and asking Google for its speed data. This takes about 30 seconds.';
			button.disabled = true;

			fetch(root.dataset.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ domain: domain })
			})
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
				.then(function (res) {
					button.disabled = false;
					if (!res.ok) {
						statusEl.className = 'azwc-audit-status error';
						statusEl.textContent = res.body && res.body.error ? res.body.error : 'Something went wrong running that audit.';
						return;
					}
					statusEl.hidden = true;
					render(res.body);
				})
				.catch(function () {
					button.disabled = false;
					statusEl.className = 'azwc-audit-status error';
					statusEl.textContent = 'The audit could not complete. If the site is slow to respond it may have timed out — try again.';
				});
		});
	})();
	</script>
	<?php
}
