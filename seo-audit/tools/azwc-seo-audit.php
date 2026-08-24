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
 * A full Lighthouse run on a heavy page genuinely takes longer than a minute.
 * 45s was cutting Google off mid-answer and reporting it as "no data".
 */
define( 'AZWC_PSI_TIMEOUT', 150 );
/** Image count at which offscreen images become a safe assumption. */
define( 'AZWC_LAZY_MANY', 10 );
/** Coverage below this, on an image-heavy page, is worth flagging. */
define( 'AZWC_LAZY_MIN_PCT', 25 );

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

/**
 * A jargon-free explanation of what each check is actually about.
 *
 * `detail` reports what was measured on this page and assumes the reader
 * already knows what a canonical tag or an Open Graph image is. Most visitors
 * do not, so on its own a real finding reads as noise. This is the paragraph
 * that tells them what the thing IS and why it costs them something.
 *
 * Keyed on the check id only, deliberately - the detail line already covers
 * what happened on this particular page, and one stable explanation per topic
 * is far easier to keep accurate than one per topic per outcome.
 */
function azwc_audit_plain_english( $id ) {
	static $map = array(
		'indexable'          => 'Search engines have to be allowed to list a page before it can ever show up in results. This checks that nothing on the page is quietly telling Google to stay away - a setting that often gets switched on while a site is being built and then never switched back off.',
		'robots_txt'         => 'robots.txt is a small file at the root of your site telling search engines which areas they may look at. If it is missing they guess; if it is too strict it can hide pages you very much want found.',
		'sitemap'            => 'A sitemap is a machine-readable list of every page you want found. It does not earn rankings by itself, but it means Google picks up new and changed pages in days rather than stumbling across them weeks later.',
		'canonical'          => 'When the same content can be reached at more than one web address, search engines have to pick which one to rank. A canonical tag makes that choice for them, so your ranking strength pools onto one page instead of being split between near-identical copies.',
		'https'              => 'This is the padlock in the browser bar. Without it browsers label the site "Not secure" in front of your customers, and Google has treated it as a ranking factor for years.',
		'redirects'          => 'How many times a browser gets bounced to a new address before it lands on the real page. Every bounce adds delay and leaks a little ranking strength, so the fewer the better.',
		'mixed_content'      => 'A secure page that pulls in an image or script over an insecure connection. Browsers may block those files outright or quietly drop the padlock, so a page that should look safe suddenly does not.',
		'compression'        => 'Compression squeezes the page before it is sent across the network, often to a quarter of its size. It is a server setting rather than a design change, and it makes every visit faster for every visitor.',
		'html_size'          => 'The weight of the page\'s own code, before any images. Very heavy pages are slower to arrive, cost mobile visitors real data, and search engines only read so far down before they stop.',
		'title'              => 'The clickable blue headline in Google\'s results, and the text on the browser tab. It is the single biggest on-page influence on whether somebody picks your listing over the competitor above or below you.',
		'description'        => 'The grey summary line underneath your headline in search results. Google does not rank you on it, but it strongly affects how many people click - and if you leave it empty, Google picks a sentence off the page for you.',
		'h1'                 => 'The main visible headline on the page. It tells visitors and search engines, in one line, what this page is actually for. Having none, or several competing ones, muddies that.',
		'content_depth'      => 'Roughly how much real text the page carries. Thin pages struggle to rank because there simply is not enough on them to answer what somebody typed into Google.',
		'image_alt'          => 'Alt text describes a picture in words. Screen readers read it aloud to blind visitors, it is an accessibility requirement you can be held to, and it is the only way image search knows what your photos show.',
		'viewport'           => 'The instruction that tells a phone to lay the page out at phone width. Without it, mobile visitors get the full desktop layout shrunk down to unreadable - and Google judges your site on its mobile version, not its desktop one.',
		'lang'               => 'A one-word declaration of which language the page is written in. Screen readers use it to choose the right pronunciation, and search engines use it to decide which country to show you in.',
		'structured_data'    => 'Hidden labels that spell out for Google what the page contains - a business, an address, opening hours, a price, a review score. This is what earns the star ratings and extra detail you see on some results and not others.',
		'open_graph'         => 'The headline and picture that appear when somebody pastes your link into Facebook, LinkedIn, WhatsApp or Slack. Without them a shared link is a bare line of text, and bare links get very few clicks.',
		'favicon'            => 'The small icon on the browser tab and in bookmarks. Google now shows it next to your listing on mobile, so not having one leaves a blank grey square beside your name.',
		'links'              => 'Internal links are how both visitors and search engines move around your site. A page nothing links to is effectively invisible, and a broken link wastes a visit you already paid to get.',
		'heading_order'      => 'Headings are meant to nest like a table of contents - a main heading, then sub-headings beneath it. When levels get skipped, screen reader users lose their place and search engines get a blurrier picture of how the page is organised.',
		'charset'            => 'A short declaration of how the text is encoded. Without it, accented letters, curly apostrophes and currency symbols can come out as garbled characters on some devices.',
		'twitter_card'       => 'The same idea as the social preview, but specifically for X/Twitter. It decides whether a shared link shows a proper image card or just a plain address.',
		'img_dimensions'     => 'The width and height written into the image tag. Without them the layout shifts around as pictures load - that is why text sometimes jumps just as you start reading. Google measures that jump and counts it against you.',
		'lazy_images'        => 'Lazy loading tells the browser not to download a picture until the visitor scrolls near it. On an image-heavy page that can cut the initial load dramatically - but it should never be used on images that are visible the moment the page opens.',
		'blank_noopener'     => 'Links that open in a new tab hand a small amount of control to the page they open, unless they are marked not to. Adding rel="noopener" closes that gap and costs nothing.',
		'empty_links'        => 'Links with nothing inside them - usually an icon, or a leftover tag. A screen reader announces them as just "link", and search engines learn nothing about where they lead.',
		'security_headers'   => 'A handful of instructions your server can send that tell the browser how to protect your visitors. They are free, invisible to customers, and they shut down several common attacks.',
	);

	return isset( $map[ $id ] ) ? $map[ $id ] : '';
}

function azwc_audit_check( $id, $label, $status, $detail, $weight = 1, $group = 'technical', $items = array() ) {
	// Looked up rather than passed in, so all 28 call sites stay untouched
	// and the copy lives in one editable place.
	$plain = azwc_audit_plain_english( $id );

	return compact( 'id', 'label', 'status', 'detail', 'plain', 'weight', 'group', 'items' );
}

/**
 * Resolve a possibly-relative reference against the audited page, so the
 * visitor is given a URL they can actually open rather than "/img/x.png".
 */
/**
 * preg_match_all that cannot return false.
 *
 * PCRE gives up with FALSE when it exceeds pcre.backtrack_limit, which large
 * pages do reach. Callers here immediately index [0], so an unguarded call
 * turns a big page into a fatal count(null). Degrade to "no matches".
 */
function azwc_audit_match_all( $pattern, $subject ) {
	$m  = array();
	$ok = @preg_match_all( $pattern, $subject, $m );
	if ( false === $ok || ! is_array( $m ) || ! isset( $m[0] ) || ! is_array( $m[0] ) ) {
		return array( array(), array() );
	}
	if ( ! isset( $m[1] ) || ! is_array( $m[1] ) ) {
		$m[1] = array();
	}
	return $m;
}

function azwc_audit_abs_url( $ref, $base ) {
	$ref = trim( html_entity_decode( $ref, ENT_QUOTES ) );
	if ( '' === $ref || 0 === stripos( $ref, 'data:' ) ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $ref ) ) {
		return $ref;
	}
	$p = wp_parse_url( $base );
	if ( empty( $p['host'] ) ) {
		return '';
	}
	$scheme = $p['scheme'] ?? 'https';
	if ( 0 === strpos( $ref, '//' ) ) {
		return $scheme . ':' . $ref;
	}
	$origin = $scheme . '://' . $p['host'];
	if ( 0 === strpos( $ref, '/' ) ) {
		return $origin . $ref;
	}
	$dir = rtrim( dirname( $p['path'] ?? '/' ), '/\\' );
	return $origin . $dir . '/' . $ref;
}

/**
 * Visible text, with script and style contents removed.
 *
 * Deliberately not a regex. A lazy `.*?` across a multi-megabyte document
 * exceeds pcre.backtrack_limit, and preg_replace signals that by returning
 * null rather than raising anything — so the caller silently sees an empty
 * string and reports a full page as having zero words. This scanner has no
 * backtracking to exhaust.
 */
function azwc_audit_strip_blocks( $html ) {
	foreach ( array( 'script', 'style', 'noscript', 'template', 'svg' ) as $tag ) {
		$offset = 0;
		while ( false !== ( $start = stripos( $html, '<' . $tag, $offset ) ) ) {
			$close = stripos( $html, '</' . $tag, $start );
			if ( false === $close ) {
				$html = substr( $html, 0, $start );
				break;
			}
			$end   = strpos( $html, '>', $close );
			$end   = false === $end ? strlen( $html ) : $end + 1;
			$html  = substr( $html, 0, $start ) . ' ' . substr( $html, $end );
			$offset = $start + 1;
		}
	}
	return $html;
}

function azwc_audit_text( $html ) {
	$stripped = azwc_audit_strip_blocks( $html );
	// strip_tags joins adjacent elements with nothing between them, so
	// "<a>one</a><a>two</a>" becomes "onetwo" and counts as a single word. On
	// minified HTML — which is most of it — that badly under-reports length.
	$stripped = str_replace( '<', ' <', $stripped );
	$plain    = wp_strip_all_tags( $stripped );
	$collapsed = preg_replace( '/\s+/', ' ', $plain );
	// preg_replace returns null on failure; fall back rather than report zero.
	return trim( null === $collapsed ? $plain : $collapsed );
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

	// A <sitemapindex> lists child sitemaps rather than pages. Counting its
	// <loc> entries as URLs reports a 400-page site as having four.
	$is_index      = $has_sitemap && false !== stripos( $sitemap['body'], '<sitemapindex' );
	$sitemap_label = ! $has_sitemap
		? ''
		: ( $is_index
			? sprintf(
				'Sitemap index found at %s, referencing %d child sitemap%s. The page count is inside those.',
				$sitemap_url,
				$sitemap_urls,
				1 === $sitemap_urls ? '' : 's'
			)
			: sprintf(
				'Sitemap found at %s listing %d URL%s.',
				$sitemap_url,
				$sitemap_urls,
				1 === $sitemap_urls ? '' : 's'
			) );

	$checks[] = azwc_audit_check(
		'sitemap',
		'An XML sitemap is reachable',
		$has_sitemap ? 'pass' : 'warn',
		$has_sitemap
			? $sitemap_label
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
			? 'Canonical points to ' . $canonical
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
				implode( ' -> ', wp_list_pluck( $chain, 'url' ) )
			),
		2,
		'technical',
		$hops > 0 ? wp_list_pluck( $chain, 'url' ) : array()
	);

	$mixed = 0;
	$mixed_items = array();
	if ( $is_https && preg_match_all( '#(?:src|href)=["\'](http://[^"\']+)#i', $html, $mm ) ) {
		$mixed = count( $mm[0] );
		$mixed_items = array_slice( array_values( array_unique( $mm[1] ) ), 0, 12 );
	}
	$checks[] = azwc_audit_check(
		'mixed_content',
		'No insecure resources on a secure page',
		$mixed > 0 ? 'fail' : 'pass',
		$mixed > 0
			? sprintf( '%d resource%s referenced over plain http on an https page. Browsers block or warn on these.', $mixed, 1 === $mixed ? ' is' : 's are' )
			: 'All referenced resources use https.',
		2,
		'technical',
		$mixed_items
	);

	$compressed = ! empty( $headers['content-encoding'] );
	$checks[]   = azwc_audit_check(
		'compression',
		'The HTML is compressed in transit',
		$compressed ? 'pass' : 'warn',
		$compressed
			? 'Content-Encoding: ' . ( is_array( $headers['content-encoding'] ) ? implode( ',', $headers['content-encoding'] ) : $headers['content-encoding'] )
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
			: sprintf( '%d characters: "%s"%s', $tlen, $title, $tlen > 60 ? ' — likely truncated in results beyond about 60.' : ( $tlen < 20 ? ' — short enough that it is probably not describing the page fully.' : '' ) ),
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
				? 'One H1: "' . trim( html_entity_decode( wp_strip_all_tags( $h1s[1][0] ), ENT_QUOTES ) ) . '"'
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
	$img_items = array();
	foreach ( $imgs[0] as $img ) {
		if ( ! preg_match( '#\balt\s*=\s*["\'][^"\']*[^\s"\']#i', $img ) ) {
			$img_noalt++;
			// Name the file, so the owner can go and fix that image.
			if ( count( $img_items ) < 12 && preg_match( '#\bsrc\s*=\s*["\']([^"\']+)#i', $img, $sm ) ) {
				$abs = azwc_audit_abs_url( $sm[1], $url );
				if ( $abs ) {
					$img_items[] = $abs;
				}
			}
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
		'onpage',
		$img_items
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
			? 'JSON-LD found describing: ' . implode( ', ', array_slice( $types, 0, 8 ) )
			: 'No JSON-LD structured data. This is what produces star ratings, FAQ dropdowns and business details in search results.',
		2,
		'structured'
	);

	$og       = (bool) preg_match( '#<meta[^>]+property=["\']og:title["\']#i', $html );
	$og_img   = (bool) preg_match( '#<meta[^>]+property=["\']og:image["\']#i', $html );
	$azw_og_label = ( $og && $og_img )
		? 'Social sharing tags are set'
		: ( $og ? 'Social sharing image is missing' : 'Social sharing tags are missing' );
	$checks[] = azwc_audit_check(
		'open_graph',
		// The heading is what people scan, so it has to state the finding. A
		// fixed "tags are set" above a body saying og:image is missing reads as
		// a contradiction.
		$azw_og_label,
		( $og && $og_img ) ? 'pass' : ( $og ? 'warn' : 'fail' ),
		( $og && $og_img )
			? 'Open Graph title and image are both present, so shared links render as a card.'
			: ( $og
				? 'og:title is set, but there is no og:image. Links shared to Facebook, LinkedIn, WhatsApp or Slack will appear as a text-only row with no thumbnail, which is markedly less clickable. Add one image of at least 1200x630px.'
				: 'No Open Graph tags at all. Links shared to Facebook, LinkedIn or WhatsApp will show a bare URL with no title, description or image.' ),
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

	// Delimiter is '~': the previous '#' delimiter collided with the '#' inside
	// the character class, so the pattern never compiled and preg_match_all
	// returned false without a warning. Every site audited reported zero links.
	preg_match_all( '~<a\b[^>]+href\s*=\s*["\']([^"\']+)["\']~i', $html, $links );
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


	/* --- additional measured checks -------------------------------------- */

	// Heading order: a jump from h1 straight to h3 breaks the document outline
	// that assistive tech and search engines both rely on.
	$hm     = azwc_audit_match_all( '#<h([1-6])\b#i', $html );
	$levels = array_map( 'intval', $hm[1] );
	$skips   = array();
	$prev    = 0;
	foreach ( $levels as $lv ) {
		if ( $prev && $lv > $prev + 1 ) {
			$skips[] = 'h' . $prev . ' followed by h' . $lv;
		}
		$prev = $lv;
	}
	$checks[] = azwc_audit_check(
		'heading_order',
		'Headings run in order without skipping levels',
		$skips ? 'warn' : 'pass',
		$skips
			? sprintf( '%d place%s where a heading level is skipped. Screen readers use the heading outline to navigate, and a gap makes the structure ambiguous.', count( $skips ), 1 === count( $skips ) ? '' : 's' )
			: sprintf( '%d headings, no skipped levels.', count( $levels ) ),
		1,
		'onpage',
		array_slice( $skips, 0, 10 )
	);

	// Character encoding.
	$has_charset = (bool) preg_match( '#<meta[^>]+charset#i', $html );
	$checks[]    = azwc_audit_check(
		'charset',
		'Character encoding is declared',
		$has_charset ? 'pass' : 'warn',
		$has_charset
			? 'A meta charset declaration is present.'
			: 'No meta charset. Browsers will guess the encoding, which can garble punctuation and accented characters.',
		1,
		'technical'
	);

	// Twitter/X card tags.
	$tw = (bool) preg_match( '#<meta[^>]+name=["\']twitter:card["\']#i', $html );
	$checks[] = azwc_audit_check(
		'twitter_card',
		'Twitter/X card tags are set',
		$tw ? 'pass' : 'warn',
		$tw
			? 'A twitter:card tag is present, so links shared on X render as a card.'
			: 'No twitter:card tag. X will fall back to Open Graph if present, otherwise the link shares as plain text.',
		1,
		'structured'
	);

	// Images without intrinsic dimensions - a direct cause of layout shift.
	$no_dims = array();
	foreach ( $imgs[0] as $img ) {
		if ( preg_match( '#\bwidth\s*=#i', $img ) && preg_match( '#\bheight\s*=#i', $img ) ) {
			continue;
		}
		if ( count( $no_dims ) < 12 && preg_match( '#\bsrc\s*=\s*["\']([^"\']+)#i', $img, $dm ) ) {
			$abs = azwc_audit_abs_url( $dm[1], $url );
			if ( $abs ) {
				$no_dims[] = $abs;
			}
		}
	}
	$dim_missing = 0;
	foreach ( $imgs[0] as $img ) {
		if ( ! preg_match( '#\bwidth\s*=#i', $img ) || ! preg_match( '#\bheight\s*=#i', $img ) ) {
			$dim_missing++;
		}
	}
	$checks[] = azwc_audit_check(
		'img_dimensions',
		'Images declare width and height',
		0 === $img_total ? 'info' : ( 0 === $dim_missing ? 'pass' : ( $dim_missing / $img_total < 0.3 ? 'warn' : 'fail' ) ),
		0 === $img_total
			? 'No images on this page.'
			: sprintf( '%d of %d images have no width/height attributes. Without them the browser cannot reserve space, so content jumps as images load - this is what Cumulative Layout Shift measures.', $dim_missing, $img_total ),
		1,
		'technical',
		$no_dims
	);

	// Lazy loading.
	$lazy = 0;
	foreach ( $imgs[0] as $img ) {
		if ( preg_match( '#\bloading\s*=\s*["\']lazy#i', $img ) ) {
			$lazy++;
		}
	}
	/**
	 * Lazy loading, scored on volume and coverage.
	 *
	 * The correct advice is to lazy-load OFFSCREEN images; applying it to an
	 * above-the-fold image delays Largest Contentful Paint and makes the page
	 * worse. Which images are offscreen cannot be determined from the HTML, so
	 * this never demands 100%: it flags only where deferrable images are a near
	 * certainty - a lot of images, and few or none of them deferred.
	 *
	 * Wording note: every branch spells the count out. "0 of 7" was read as
	 * "0 or 7", which made a real finding look like a bug.
	 */
	$azw_lazy_heavy = $img_total >= AZWC_LAZY_MANY;
	$azw_lazy_pct   = $img_total > 0 ? (int) round( $lazy / $img_total * 100 ) : 0;

	if ( 0 === $img_total ) {
		$azw_lazy_status = 'info';
		$azw_lazy_label  = 'No images to lazy load';
		$azw_lazy_detail = 'This page has no images, so there is nothing to defer.';
	} elseif ( 0 === $lazy ) {
		$azw_lazy_status = $azw_lazy_heavy ? 'warn' : 'info';
		$azw_lazy_label  = 'Images are not lazy loaded';
		$azw_lazy_detail = $azw_lazy_heavy
			? sprintf( 'This page loads %d images and not one of them uses loading="lazy". At that many, most sit below the fold and are being downloaded before anyone scrolls to them. Add it to those - but leave it off anything visible on load, because lazy-loading an above-the-fold image delays Largest Contentful Paint.', $img_total )
			: sprintf( 'This page loads %d images and none use loading="lazy". At that few it makes little practical difference, and it should never be applied to images visible on load - that delays Largest Contentful Paint. Noted rather than counted against you.', $img_total );
	} elseif ( $azw_lazy_heavy && $azw_lazy_pct < AZWC_LAZY_MIN_PCT ) {
		$azw_lazy_status = 'warn';
		$azw_lazy_label  = 'Most images are not lazy loaded';
		$azw_lazy_detail = sprintf( 'This page loads %d images and only %d of them use loading="lazy" - %d%% coverage. On a page this image-heavy, far more than %d%% are likely to be below the fold, so the rest are downloaded up front. Keep it off whatever is visible on load.', $img_total, $lazy, $azw_lazy_pct, $azw_lazy_pct );
	} else {
		$azw_lazy_status = 'pass';
		$azw_lazy_label  = 'Images use native lazy loading';
		$azw_lazy_detail = sprintf( 'This page loads %d images and %d of them use loading="lazy" - %d%% coverage, so offscreen images are deferred until they are needed.', $img_total, $lazy, $azw_lazy_pct );
	}

	$checks[] = azwc_audit_check(
		'lazy_images',
		$azw_lazy_label,
		$azw_lazy_status,
		$azw_lazy_detail,
		1,
		'technical'
	);

	// target="_blank" without rel=noopener lets the opened page reach back into
	// this one through window.opener.
	$blanks = azwc_audit_match_all( '#<a\b[^>]*target\s*=\s*["\']_blank["\'][^>]*>#i', $html );
	$unsafe = array();
	foreach ( $blanks[0] as $a ) {
		if ( preg_match( '#\brel\s*=\s*["\'][^"\']*noopener#i', $a ) ) {
			continue;
		}
		if ( count( $unsafe ) < 10 && preg_match( '#\bhref\s*=\s*["\']([^"\']+)#i', $a, $hm2 ) ) {
			$unsafe[] = azwc_audit_abs_url( $hm2[1], $url ) ?: $hm2[1];
		}
	}
	$checks[] = azwc_audit_check(
		'blank_noopener',
		'New-tab links are opened safely',
		$unsafe ? 'warn' : 'pass',
		$unsafe
			? sprintf( '%d link%s open a new tab without rel="noopener". The opened page can reference window.opener and redirect this tab.', count( $unsafe ), 1 === count( $unsafe ) ? '' : 's' )
			: sprintf( '%d new-tab link%s, all carrying rel="noopener".', count( $blanks[0] ), 1 === count( $blanks[0] ) ? '' : 's' ),
		1,
		'technical',
		$unsafe
	);

	// Placeholder links.
	$empties = azwc_audit_match_all( '#<a\b[^>]*href\s*=\s*["\'](#|)["\'][^>]*>#i', $html );
	$empty_n  = count( $empties[0] );
	$checks[] = azwc_audit_check(
		'empty_links',
		'No placeholder or empty links',
		$empty_n > 0 ? 'warn' : 'pass',
		$empty_n > 0
			? sprintf( '%d link%s point at "#" or nothing. Crawlers follow these and find no destination, and keyboard users land on a control that does not go anywhere.', $empty_n, 1 === $empty_n ? '' : 's' )
			: 'Every link has a real destination.',
		1,
		'onpage'
	);

	// Response security headers.
	$hdr = function ( $name ) use ( $headers ) {
		$v = $headers[ $name ] ?? '';
		return is_array( $v ) ? implode( ',', $v ) : (string) $v;
	};
	$sec_present = array();
	$sec_missing = array();
	foreach ( array(
		'strict-transport-security' => 'HSTS',
		'x-content-type-options'    => 'X-Content-Type-Options',
		'x-frame-options'           => 'X-Frame-Options',
		'referrer-policy'           => 'Referrer-Policy',
	) as $key => $label ) {
		if ( '' !== $hdr( $key ) ) {
			$sec_present[] = $label;
		} else {
			$sec_missing[] = $label;
		}
	}
	$checks[] = azwc_audit_check(
		'security_headers',
		'Common security headers are sent',
		empty( $sec_missing ) ? 'pass' : ( count( $sec_missing ) <= 2 ? 'warn' : 'fail' ),
		empty( $sec_missing )
			? 'All four checked headers are present: ' . implode( ', ', $sec_present ) . '.'
			: sprintf(
				'Present: %s. Missing: %s. These are set by the server and cost nothing to add.',
				$sec_present ? implode( ', ', $sec_present ) : 'none',
				implode( ', ', $sec_missing )
			),
		1,
		'technical'
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

	$r = wp_remote_get( $endpoint, array( 'timeout' => AZWC_PSI_TIMEOUT ) );
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
 *
 * Split into stages the client requests in turn, rather than one long call.
 *
 * The reason is honesty about timing as much as user experience. The audit does
 * genuinely separate work — fetch the page, walk the redirect chain, read
 * robots.txt and the sitemap, then ask Google twice — and PageSpeed alone can
 * take twenty seconds per strategy. Returning it all at the end means either a
 * spinner that explains nothing for half a minute, or, when PageSpeed quietly
 * fails, a result so fast the visitor assumes nothing happened.
 *
 * Staged, the progress shown corresponds to work actually in flight. Nothing
 * here pads the clock: if a stage is fast, it reports fast.
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'azwc/v1', '/audit', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'domain'   => array( 'required' => true, 'type' => 'string' ),
			'stage'    => array( 'required' => false, 'type' => 'string', 'default' => 'site' ),
			'strategy' => array( 'required' => false, 'type' => 'string', 'default' => 'mobile' ),
			// peek: read the cache only, never start a PageSpeed call.
			'peek'     => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
		),
		'callback'            => 'azwc_audit_rest',
	) );
} );

function azwc_audit_rest( WP_REST_Request $request ) {
	$url = azwc_audit_normalize( $request->get_param( 'domain' ) );
	if ( is_wp_error( $url ) ) {
		return new WP_REST_Response( array( 'error' => $url->get_error_message() ), 400 );
	}

	$stage    = $request->get_param( 'stage' );
	$strategy = 'desktop' === $request->get_param( 'strategy' ) ? 'desktop' : 'mobile';

	if ( 'psi' === $stage ) {
		return azwc_audit_stage_psi( $url, $strategy, (bool) $request->get_param( 'peek' ) );
	}
	return azwc_audit_stage_site( $url );
}

/**
 * Turn a transport-layer failure into something a business owner can act on.
 *
 * The raw text here is cURL's, and it is written for whoever is holding the
 * socket. The underlying fact is still reported; only the phrasing changes,
 * and the original is appended so nothing is hidden.
 */
function azwc_audit_human_error( $raw ) {
	$lower = strtolower( $raw );

	if ( false !== strpos( $lower, 'timed out' ) || false !== strpos( $lower, 'timeout' ) ) {
		$plain = 'Your server did not respond in time. That usually means the site is very slow, temporarily down, or blocking automated requests - a firewall or security plugin refusing anything that is not a browser is the most common cause.';
	} elseif ( false !== strpos( $lower, 'could not resolve host' ) || false !== strpos( $lower, 'name or service not known' ) ) {
		$plain = 'That domain name did not resolve, so no server could be found for it. Check the spelling - and if it is newly registered, DNS can take a day or so to propagate.';
	} elseif ( false !== strpos( $lower, 'ssl' ) || false !== strpos( $lower, 'certificate' ) ) {
		$plain = 'The site\'s HTTPS certificate could not be verified. Visitors are likely seeing a browser security warning, which is worth fixing before anything else on this report.';
	} elseif ( false !== strpos( $lower, 'connection refused' ) ) {
		$plain = 'The server actively refused the connection. The domain resolves, but nothing is answering web requests on it right now.';
	} else {
		$plain = 'The site could not be reached.';
	}

	return $plain . ' (Technical detail: ' . $raw . ')';
}

/**
 * Same idea for an HTTP status the server did return. A 403 is usually a bot
 * block rather than a broken site, and saying so prevents the visitor
 * concluding their site is down when it is fine in a browser.
 */
function azwc_audit_http_status_error( $status ) {
	$map = array(
		401 => 'That page requires a login, so there is no public page to analyse.',
		403 => 'The server refused the request (HTTP 403). The site is probably fine in a browser - a firewall or bot protection is blocking automated tools like this one. Anything that blocks us may also be blocking search engine crawlers, which is worth checking.',
		404 => 'That address returned "not found" (HTTP 404). Check the URL, or try the home page on its own.',
		410 => 'That page reports itself as permanently removed (HTTP 410).',
		429 => 'The site is rate-limiting requests (HTTP 429), so it declined to serve the page. Try again shortly.',
		500 => 'The site returned a server error (HTTP 500). That is a fault on the site itself and visitors are likely seeing it too.',
		502 => 'The site returned a bad-gateway error (HTTP 502), which usually means its own server is having trouble right now.',
		503 => 'The site is unavailable (HTTP 503) - often maintenance mode, or a server under too much load.',
	);

	if ( isset( $map[ $status ] ) ) {
		return $map[ $status ];
	}

	return sprintf(
		'That URL returned HTTP %d, so there is no page to analyse.%s',
		$status,
		$status >= 500 ? ' A 5xx status is a fault on the site\'s own server.' : ''
	);
}

/** Everything measurable from the site itself. */
function azwc_audit_stage_site( $url ) {
	$cache_key = 'azwc_audit_site_' . md5( $url );
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
			array( 'error' => azwc_audit_human_error( $page->get_error_message() ) ),
			422
		);
	}
	if ( $page['status'] >= 400 ) {
		return new WP_REST_Response(
			array( 'error' => azwc_audit_http_status_error( $page['status'] ) ),
			422
		);
	}

	$chain  = azwc_audit_chain( $url );
	$checks = azwc_audit_run_checks( $url, $page, $chain );

	$result = array(
		'url'       => $url,
		'fetched'   => gmdate( 'c' ),
		'status'    => $page['status'],
		'ms'        => $page['ms'],
		'bytes'     => strlen( $page['body'] ),
		'score'     => azwc_audit_score( $checks ),
		'checks'    => $checks,
		'authority' => azwc_audit_authority( $url ),
		'cached'    => false,
	);

	set_transient( $cache_key, $result, AZWC_AUDIT_CACHE_HOURS * HOUR_IN_SECONDS );
	return new WP_REST_Response( $result, 200 );
}

/** One PageSpeed strategy. Cached separately so a slow half does not block. */
function azwc_audit_stage_psi( $url, $strategy, $peek = false ) {
	/**
	 * Finish the PageSpeed call even if the connection is dropped.
	 *
	 * Running both strategies concurrently makes Google slower per call
	 * (55-69s measured, against 37-56s sequential), which lands them on the
	 * edge of the ~60s proxy cap in front of this site. Without this, a
	 * killed connection also kills the PHP process, discarding a request
	 * that was seconds from completing - so the retry pays full price again.
	 * With it, the result still reaches the cache and the client's retry is
	 * an instant cache read.
	 */
	if ( function_exists( 'ignore_user_abort' ) ) {
		@ignore_user_abort( true );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( AZWC_PSI_TIMEOUT + 30 );
	}

	$cache_key = 'azwc_audit_psi_' . $strategy . '_' . md5( $url );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return new WP_REST_Response( array( 'psi' => $cached, 'strategy' => $strategy, 'cached' => true ), 200 );
	}

	/**
	 * Peek: the caller only wants to know whether a result has landed yet.
	 *
	 * A plain retry is indistinguishable from a first request, so it would
	 * start another ~60s PageSpeed call on every poll. A peek costs nothing,
	 * letting the client wait while an earlier, disconnected request finishes
	 * and writes its result.
	 */
	if ( $peek ) {
		return new WP_REST_Response(
			array( 'psi' => null, 'strategy' => $strategy, 'pending' => true, 'cached' => false ),
			200
		);
	}

	$psi = azwc_audit_psi( $url, $strategy );

	// Cache the failure too, briefly. Without a key Google throttles hard, and
	// retrying on every page load makes the throttling worse rather than better.
	// A successful result is worth keeping; a failure is cached only briefly so
	// the client's retry can pick up a late-arriving success.
	set_transient( $cache_key, $psi, $psi ? AZWC_AUDIT_CACHE_HOURS * HOUR_IN_SECONDS : 20 );

	return new WP_REST_Response( array( 'psi' => $psi, 'strategy' => $strategy, 'cached' => false ), 200 );
}


/* -------------------------------------------------------------------------
 * Front end
 * ---------------------------------------------------------------------- */

add_shortcode( 'azwc_seo_audit', 'azwc_audit_shortcode' );

/**
 * Where the dedicated results tab lives. Falls back to the audit page
 * itself, so the tool still works if that page is ever removed.
 */
function azwc_audit_results_url() {
	$page = get_page_by_path( 'seo-audit-results' );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}
	return '';
}

function azwc_audit_shortcode() {
	ob_start();
	?>
	<div id="azwc-audit"
	     data-endpoint="<?php echo esc_url( rest_url( 'azwc/v1/audit' ) ); ?>"
	     data-results="<?php echo esc_url( azwc_audit_results_url() ); ?>">
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
		<div class="azwc-scan" hidden>
			<div class="azwc-scan-head">
				<h4>Scanning <span class="azwc-scan-target"></span></h4>
				<p class="azwc-scan-count"></p>
			</div>
			<div class="azwc-tiles" role="img" aria-label="Audit checks, filling in as each result lands"></div>
			<div class="azwc-scan-key">
				<span><i class="k-pass"></i>Passing</span>
				<span><i class="k-warn"></i>Worth improving</span>
				<span><i class="k-fail"></i>Needs fixing</span>
				<span><i class="k-pending"></i>Still checking</span>
			</div>
		</div>
		<div class="azwc-progress" hidden>
			<h4>Running the audit <span class="azwc-elapsed" aria-hidden="true"></span></h4>
			<p class="azwc-sub">Each step below finishes when that request actually returns.</p>
			<div class="azwc-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Audit progress">
				<span class="azwc-bar-fill"></span>
			</div>
			<ul class="azwc-steps"></ul>
		</div>
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
	#azwc-audit .azwc-scan{margin-top:18px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:24px}
	#azwc-audit .azwc-scan-head{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:baseline;margin-bottom:14px}
	#azwc-audit .azwc-scan-head h4{margin:0;font-size:15px}
	#azwc-audit .azwc-scan-target{color:var(--muted);font-weight:500;word-break:break-all}
	#azwc-audit .azwc-scan-count{margin:0;font-size:12.5px;color:var(--muted);font-variant-numeric:tabular-nums}
	#azwc-audit .azwc-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(26px,1fr));gap:6px}
	#azwc-audit .azwc-tile{display:block;height:26px;border-radius:5px;background:rgba(127,127,127,.20);
		transform:scale(.82);opacity:.55;transition:background .25s ease,transform .25s ease,opacity .25s ease}
	#azwc-audit .azwc-tile.is-pending{animation:azwc-tilepulse 1.25s ease-in-out infinite}
	#azwc-audit .azwc-tile.is-pass{background:#0f9d58;transform:scale(1);opacity:1}
	#azwc-audit .azwc-tile.is-warn{background:#e8a33d;transform:scale(1);opacity:1}
	#azwc-audit .azwc-tile.is-fail{background:#d64545;transform:scale(1);opacity:1}
	#azwc-audit .azwc-tile.is-info{background:#9aa3ad;transform:scale(1);opacity:.9}
	@keyframes azwc-tilepulse{0%,100%{opacity:.35}50%{opacity:.75}}
	@media(prefers-reduced-motion:reduce){#azwc-audit .azwc-tile{transition:none}#azwc-audit .azwc-tile.is-pending{animation:none}}
	#azwc-audit .azwc-scan-key{display:flex;flex-wrap:wrap;gap:14px;margin-top:14px;font-size:12px;color:var(--muted)}
	#azwc-audit .azwc-scan-key i{display:inline-block;width:10px;height:10px;margin-right:6px;border-radius:3px;vertical-align:-1px}
	#azwc-audit .azwc-scan-key .k-pass{background:#0f9d58}
	#azwc-audit .azwc-scan-key .k-warn{background:#e8a33d}
	#azwc-audit .azwc-scan-key .k-fail{background:#d64545}
	#azwc-audit .azwc-scan-key .k-pending{background:rgba(127,127,127,.45)}
	#azwc-audit.is-results .azwc-audit-form{display:none}
	#azwc-audit .azwc-progress{margin-top:18px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:24px}
	#azwc-audit .azwc-progress h4{margin:0 0 4px;font-size:15px}
	#azwc-audit .azwc-progress .azwc-sub{margin-bottom:14px}
	#azwc-audit .azwc-elapsed{float:right;font-size:12px;font-weight:600;color:var(--muted);font-variant-numeric:tabular-nums}
	#azwc-audit .azwc-bar{position:relative;height:6px;margin:0 0 18px;background:rgba(127,127,127,.22);border-radius:99px;overflow:hidden}
	#azwc-audit .azwc-bar-fill{display:block;height:100%;width:0;border-radius:99px;background:linear-gradient(90deg,#e6b84d,#f5d47d);transition:width .45s ease}
	/* While a stage is still in flight the bar keeps moving, so a long
	   PageSpeed wait does not look like a stall. */
	#azwc-audit .azwc-bar.is-running .azwc-bar-fill::after{content:"";position:absolute;inset:0;border-radius:99px;
		background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);animation:azwc-sheen 1.5s linear infinite}
	@keyframes azwc-sheen{from{transform:translateX(-100%)}to{transform:translateX(100%)}}
	@media(prefers-reduced-motion:reduce){#azwc-audit .azwc-bar.is-running .azwc-bar-fill::after{animation:none}}
	#azwc-audit .azwc-pending{display:flex;align-items:center;gap:9px;margin:0 0 16px;padding:11px 14px;border-radius:10px;
		font-size:13px;background:rgba(230,184,77,.10);border:1px solid rgba(230,184,77,.34);color:#8a6410}
	#azseo-tool-mount #azwc-audit .azwc-pending{color:#f0d9a2}
	#azwc-audit .azwc-pending{display:block}
	#azwc-audit .azwc-pending-head{display:flex;align-items:center;gap:9px}
	#azwc-audit .azwc-pending-bar{display:block;position:relative;height:5px;margin:10px 0 0;border-radius:99px;overflow:hidden;background:rgba(127,127,127,.25)}
	#azwc-audit .azwc-pending-bar span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#e6b84d,#f5d47d);transition:width .45s ease}
	#azwc-audit .azwc-pending-bar::after{content:"";position:absolute;inset:0;border-radius:99px;
		background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);animation:azwc-sheen 1.5s linear infinite}
	@media(prefers-reduced-motion:reduce){#azwc-audit .azwc-pending-bar::after{animation:none}}
	#azwc-audit .azwc-pending i{width:12px;height:12px;border-radius:50%;border:2px solid #e6b84d;border-top-color:transparent;animation:azwc-spin .8s linear infinite}
	@media(prefers-reduced-motion:reduce){#azwc-audit .azwc-pending i{animation:none}}
	#azwc-audit .azwc-steps{display:grid;gap:11px;margin:0;padding:0;list-style:none}
	#azwc-audit .azwc-step{display:grid;grid-template-columns:20px 1fr;gap:11px;align-items:start;font-size:13.5px;color:#9aa3ad;transition:color .2s}
	#azwc-audit .azwc-step.active{color:var(--ink);font-weight:650}
	#azwc-audit .azwc-step.done{color:var(--ink)}
	#azwc-audit .azwc-step small{display:block;font-weight:400;color:var(--muted);font-size:12px}
	#azwc-audit .azwc-mark{width:16px;height:16px;margin-top:3px;border-radius:50%;border:2px solid #d6dbe1;position:relative}
	#azwc-audit .azwc-step.active .azwc-mark{border-color:var(--accent);border-top-color:transparent;animation:azwc-spin .7s linear infinite}
	#azwc-audit .azwc-step.done .azwc-mark{border-color:var(--pass);background:var(--pass)}
	#azwc-audit .azwc-step.done .azwc-mark:after{content:"";position:absolute;left:4px;top:1px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
	#azwc-audit .azwc-step.skip .azwc-mark{border-color:#d6dbe1;background:#eceff3}
	@keyframes azwc-spin{to{transform:rotate(360deg)}}
	@media(prefers-reduced-motion:reduce){#azwc-audit .azwc-step.active .azwc-mark{animation:none;border-top-color:var(--accent)}}
	#azwc-audit .azwc-reveal{animation:azwc-fade .25s ease both}
	@keyframes azwc-fade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
	#azwc-audit .azwc-panel{margin-top:22px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:26px}
	#azwc-audit h3{margin:0 0 4px;font-size:19px;letter-spacing:-.01em}
	#azwc-audit .azwc-sub{margin:0 0 20px;color:var(--muted);font-size:13px}
	#azwc-audit .azwc-top{display:grid;grid-template-columns:200px 1fr;gap:32px;align-items:center}
	#azwc-audit .azwc-gauge{text-align:center}
	#azwc-audit .azwc-gauge svg text{fill:var(--ink)}
	#azwc-audit .azwc-gauge figcaption{margin-top:6px;font-size:12px;color:var(--muted)}
	#azwc-audit .azwc-bars{display:grid;gap:13px}
	#azwc-audit .azwc-bar-row{display:grid;grid-template-columns:130px 1fr 44px;gap:12px;align-items:center;font-size:13px}
	#azwc-audit .azwc-track{height:9px;background:#eceff3;border-radius:99px;overflow:hidden}
	#azwc-audit .azwc-fill{display:block;height:100%;border-radius:99px}
#azwc-audit .azwc-gauge figcaption{background:none!important;padding:0!important;color:var(--muted)!important}
#azwc-audit .azwc-vital{padding:16px;background:var(--bg);border:1px solid var(--line);border-radius:11px}
#azwc-audit .azwc-vital b{display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
#azwc-audit .azwc-vital strong{display:block;margin-top:7px;font-size:25px;font-weight:750;letter-spacing:-.02em;line-height:1.1}
#azwc-audit .azwc-vital .azwc-band{display:block;font-size:12px;font-weight:750;margin-top:3px}
#azwc-audit .azwc-thresh{position:relative;display:flex;gap:2px;margin:11px 0 9px}
#azwc-audit .azwc-thresh i{display:block;height:6px;border-radius:99px;opacity:.30}
#azwc-audit .azwc-thresh i.on{opacity:1}
#azwc-audit .azwc-thresh i.g{flex:0 0 33%;background:#0f9d58}
#azwc-audit .azwc-thresh i.n{flex:0 0 34%;background:#e8a33d}
#azwc-audit .azwc-thresh i.p{flex:1 1 auto;background:#d64545}
#azwc-audit .azwc-thresh u{position:absolute;top:-4px;width:3px;height:14px;background:#111827;border-radius:2px;transform:translateX(-50%)}
#azwc-audit .azwc-vital em{display:block;font-style:normal;font-size:12px;color:var(--muted);line-height:1.45}
#azwc-audit .azwc-scale{display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-top:-4px;margin-bottom:8px}
	#azwc-audit .azwc-bar-val{text-align:right;font-weight:800;font-variant-numeric:tabular-nums}
	#azwc-audit .azwc-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:12px}
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
	#azwc-audit .azwc-actions{counter-reset:azwcfix}
	#azwc-audit .azwc-action{display:grid;grid-template-columns:34px 1fr;gap:14px;padding:16px 0;border-bottom:1px solid var(--line)}
	#azwc-audit .azwc-action:last-child{border-bottom:0}
	#azwc-audit .azwc-action-num{counter-increment:azwcfix;display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:13px;font-weight:800;color:#161208;background:var(--accent)}
	#azwc-audit .azwc-action-num::before{content:counter(azwcfix)}
	#azwc-audit .azwc-action h4{margin:3px 0 4px;font-size:14.5px;font-weight:750}
	#azwc-audit .azwc-action p{margin:0;color:var(--muted);font-size:13px}
	#azwc-audit .azwc-sev{display:inline-block;margin-left:8px;padding:1px 8px;border-radius:99px;font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;vertical-align:2px}
	#azwc-audit .azwc-sev.fail{background:rgba(214,69,69,.15);color:#d64545}
	#azwc-audit .azwc-sev.warn{background:rgba(232,163,61,.16);color:#b97d1b}
	#azseo-tool-mount #azwc-audit .azwc-sev.warn{color:#e8a33d}
	#azwc-audit .azwc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(104px,1fr));gap:10px;margin:18px 0 0}
	#azwc-audit .azwc-stat{padding:12px 14px;background:var(--bg);border:1px solid var(--line);border-radius:10px}
	#azwc-audit .azwc-stat b{display:block;font-size:22px;font-weight:780;letter-spacing:-.02em}
	#azwc-audit .azwc-stat span{display:block;margin-top:2px;font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)}
	#azwc-audit .azwc-allclear{padding:18px;border-radius:11px;background:rgba(15,157,88,.10);border:1px solid rgba(15,157,88,.34);color:#0f9d58;font-size:14px;font-weight:650}
	#azwc-audit .azwc-plain{margin:9px 0 0;padding:9px 12px;border-left:3px solid var(--accent,#e6b84d);
		background:rgba(127,127,127,.08);border-radius:0 8px 8px 0;font-size:13px;line-height:1.62;color:var(--muted)}
	#azwc-audit .azwc-plain b{display:block;margin-bottom:2px;font-size:10.5px;font-weight:800;
		letter-spacing:.09em;text-transform:uppercase;opacity:.75}
	#azwc-audit .azwc-items{margin:9px 0 0;padding:0;list-style:none;display:grid;gap:4px}
	#azwc-audit .azwc-items li{font-size:12.5px;line-height:1.45;word-break:break-all}
	#azwc-audit .azwc-items a{color:#9b711b;text-decoration:none}
	#azwc-audit .azwc-items a:hover,#azwc-audit .azwc-items a:focus{text-decoration:underline}
	#azseo-tool-mount #azwc-audit .azwc-items a{color:#e6b84d}
	#azwc-audit .azwc-items-more{color:var(--muted);font-size:12px;margin-top:3px}
	#azwc-audit .azwc-step small.azwc-target{display:block;font-size:11.5px;color:var(--muted);word-break:break-all}
	#azwc-audit .azwc-legend{display:flex;flex-wrap:wrap;gap:16px;margin-bottom:18px;font-size:12px;color:var(--muted)}
	#azwc-audit .azwc-legend i{display:inline-block;width:10px;height:10px;margin-right:6px;border-radius:50%;vertical-align:-1px}
	#azwc-audit .azwc-empty{padding:18px;background:var(--bg);border:1px dashed #cfd6de;border-radius:11px;color:var(--muted);font-size:13.5px}
	#azwc-audit .azwc-cta{margin-top:22px;padding:26px;background:#0b192e;border-radius:14px;color:#fff}
	#azwc-audit .azwc-cta h3{color:#fff}
	#azwc-audit .azwc-cta p{margin:8px 0 16px;color:#c2cad7;font-size:14px}
	#azwc-audit .azwc-cta a{display:inline-flex;align-items:center;min-height:46px;padding:0 22px;background:var(--accent);color:#12203a;border-radius:9px;font-weight:800;font-size:14px;text-decoration:none}
	@media(max-width:640px){#azwc-audit .azwc-top{grid-template-columns:1fr}#azwc-audit .azwc-bar-row{grid-template-columns:96px 1fr 40px}}
	
	/* --- dark host context -------------------------------------------
	   When the gold/black page design adopts this tool into its own mount,
	   re-point the tool's custom properties at that palette. Without this
	   the card stays white while the host styles the input dark and the
	   label light, so the label disappears against its own card. */
	#azseo-tool-mount #azwc-audit{--ink:#e6edf3;--muted:#9aa7b4;--line:rgba(255,255,255,.10);--card:#0b0e13;--bg:#111820;--accent:#e6b84d}
	#azseo-tool-mount #azwc-audit label{color:var(--ink)}
	
	/* Beats the page design's own !important input rule (2 ids + attr). */
	#azseo-page #azseo-tool-mount #azwc-audit input,
	#azseo-page #azseo-tool-mount #azwc-audit input[type="text"],
	#azseo-page #azseo-tool-mount #azwc-audit input[type="url"]{background:#161d26!important;color:#e6edf3!important;border:1px solid rgba(230,184,77,.45)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.05)!important}
	#azseo-page #azseo-tool-mount #azwc-audit input:hover{border-color:rgba(230,184,77,.65)!important}
	#azseo-page #azseo-tool-mount #azwc-audit input:focus{background:#1b2430!important;border-color:#e6b84d!important;outline:2px solid #e6b84d!important;outline-offset:1px}
	#azseo-page #azseo-tool-mount #azwc-audit input::placeholder{color:#9fb0c0!important;opacity:1!important}
	#azseo-tool-mount #azwc-audit input{background:#161d26;color:#e6edf3;border:1px solid rgba(230,184,77,.42);box-shadow:inset 0 1px 0 rgba(255,255,255,.04)}
	#azseo-tool-mount #azwc-audit input:hover{border-color:rgba(230,184,77,.60)}
	#azseo-tool-mount #azwc-audit input:focus{outline:2px solid #e6b84d;outline-offset:1px;border-color:#e6b84d;background:#1b2430}
	#azseo-tool-mount #azwc-audit input::placeholder{color:#9fb0c0;opacity:1}
	#azseo-tool-mount #azwc-audit button{color:#161208}
	#azseo-tool-mount #azwc-audit .azwc-track{background:rgba(255,255,255,.10)}
	#azseo-tool-mount #azwc-audit .azwc-empty{border-color:rgba(255,255,255,.16)}
	#azseo-tool-mount #azwc-audit .azwc-audit-status{background:rgba(230,184,77,.10);border-color:rgba(230,184,77,.32);color:#f0d9a2}
	#azseo-tool-mount #azwc-audit .azwc-audit-status.error{background:rgba(214,69,69,.12);border-color:rgba(214,69,69,.38);color:#f3b4b4}
	#azseo-tool-mount #azwc-audit .azwc-mark{border-color:rgba(255,255,255,.28)}
	#azseo-tool-mount #azwc-audit .azwc-step{color:#7d8894}
	#azseo-tool-mount #azwc-audit .azwc-step.done,#azseo-tool-mount #azwc-audit .azwc-step.active{color:var(--ink)}
	#azseo-tool-mount #azwc-audit .azwc-cta{background:#111820;border:1px solid rgba(230,184,77,.30)}
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
		var progress = root.querySelector('.azwc-progress');

		var COLOR = { pass: '#0f9d58', warn: '#e8a33d', fail: '#d64545', info: '#9aa3ad' };
		var FIELD_LABELS = {
			CUMULATIVE_LAYOUT_SHIFT_SCORE: 'Layout shift',
			EXPERIMENTAL_TIME_TO_FIRST_BYTE: 'Time to first byte',
			TIME_TO_FIRST_BYTE: 'Time to first byte',
			FIRST_CONTENTFUL_PAINT_MS: 'First contentful paint',
			LARGEST_CONTENTFUL_PAINT_MS: 'Largest contentful paint',
			INTERACTION_TO_NEXT_PAINT: 'Interaction to next paint',
			EXPERIMENTAL_INTERACTION_TO_NEXT_PAINT: 'Interaction to next paint',
			FIRST_INPUT_DELAY_MS: 'First input delay'
		};
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
			var pending = psi && (psi.mobile === undefined || psi.desktop === undefined);
			var have = ['mobile', 'desktop'].filter(function (k) { return psi && psi[k]; });
			if (!have.length && pending) {
				return '<section class="azwc-panel"><h3>Speed</h3>'
					+ '<p class="azwc-sub">Google PageSpeed Insights</p>'
					+ '<div class="azwc-empty">Waiting on Google&rsquo;s API&hellip;</div></section>';
			}
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
					+ vital('lcp', m.lcp)
					+ vital('cls', m.cls)
					+ vital('tbt', m.tbt)
					+ vital('fcp', m.fcp)
					+ '</div>';

				var f = p.field || {};
				var keys = Object.keys(f);
				if (keys.length) {
					html += '<p class="azwc-sub" style="margin:14px 0 8px">Field data from real visitors (Chrome UX Report):</p><div class="azwc-metrics">';
					keys.forEach(function (fk) {
						var label = FIELD_LABELS[fk] || fk.replace(/_/g, ' ').toLowerCase();
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

		/* Google's published Core Web Vitals thresholds. These are Google's own
   numbers, not our grading - the marker shows where this site actually
   lands, which is the difference between "4.1 s" and "4.1 s, which is
   poor, and here is why that matters". */
		var VITALS = {
			lcp: { label: 'Largest contentful paint', plain: 'How long before the main content appears on screen.', good: 2500, poor: 4000, fmt: 's' },
			cls: { label: 'Cumulative layout shift', plain: 'How much the page jumps around while it loads.', good: 0.1, poor: 0.25, fmt: 'n' },
			tbt: { label: 'Total blocking time', plain: 'How long the page stays frozen and ignores taps.', good: 200, poor: 600, fmt: 'ms' },
			fcp: { label: 'First contentful paint', plain: 'How long before anything at all appears.', good: 1800, poor: 3000, fmt: 's' }
		};

		function vital(key, m) {
			var cfg = VITALS[key];
			if (!cfg || !m || (m.display === null && m.value === null)) { return ''; }
			var v = m.value;
			var band = -1;
			if (typeof v === 'number') { band = v <= cfg.good ? 0 : (v <= cfg.poor ? 1 : 2); }
			var names = ['Good', 'Needs improvement', 'Poor'];
			var cols = [COLOR.pass, COLOR.warn, COLOR.fail];
			var bandName = names[band] || '';
			var bandColor = cols[band] || '#9aa3ad';

			// Marker position across three equal visual segments.
			var pct = null;
			if (band === 0) { pct = Math.max(2, (v / cfg.good) * 33); }
			else if (band === 1) { pct = 33 + ((v - cfg.good) / (cfg.poor - cfg.good)) * 34; }
			else if (band === 2) { pct = Math.min(98, 67 + ((v - cfg.poor) / (cfg.poor * 1.5)) * 33); }

			var unit = cfg.fmt === 'n' ? '' : (cfg.fmt === 's' ? ' s' : ' ms');
			var goodLabel = cfg.fmt === 's' ? (cfg.good / 1000) + ' s' : (cfg.good + unit);
			var poorLabel = cfg.fmt === 's' ? (cfg.poor / 1000) + ' s' : (cfg.poor + unit);

			return '<div class="azwc-vital">'
				+ '<b>' + esc(cfg.label) + '</b>'
				+ '<strong style="color:' + bandColor + '">' + esc(m.display || v) + '</strong>'
				+ (bandName ? '<span class="azwc-band" style="color:' + bandColor + '">' + bandName + '</span>' : '')
				+ '<span class="azwc-thresh">'
				+   '<i class="g' + (band === 0 ? ' on' : '') + '"></i>'
				+   '<i class="n' + (band === 1 ? ' on' : '') + '"></i>'
				+   '<i class="p' + (band === 2 ? ' on' : '') + '"></i>'
				+   (pct === null ? '' : '<u style="left:' + pct.toFixed(1) + '%"></u>')
				+ '</span>'
				+ '<span class="azwc-scale"><span>good ≤ ' + esc(goodLabel) + '</span><span>poor &gt; ' + esc(poorLabel) + '</span></span>'
				+ '<em>' + esc(cfg.plain) + '</em>'
				+ '</div>';
		}

		function metric(label, m) {
			if (!m || (m.display === null && m.value === null)) { return ''; }
			var color = m.score === null || m.score === undefined ? '#111827' : scoreColor(m.score * 100);
			return '<div class="azwc-metric"><b>' + esc(label) + '</b>'
				+ '<strong style="color:' + color + '">' + esc(m.display || m.value) + '</strong></div>';
		}

		/** The jargon-free explainer, when the check carries one. */
		function plainBlock(c) {
			if (!c.plain) { return ''; }
			return '<div class="azwc-plain"><b>What this means</b>' + esc(c.plain) + '</div>';
		}

		function checksPanel(checks) {
			var order = { fail: 0, warn: 1, pass: 2, info: 3 };
			var sorted = checks.slice().sort(function (a, b) { return order[a.status] - order[b.status]; });
			var items = sorted.map(function (c) {
				// A count is not actionable on its own - list the offending URLs
				// when the check was able to collect them.
				var list = '';
				if (c.items && c.items.length) {
					list = '<ul class="azwc-items">' + c.items.map(function (u) {
						return '<li><a href="' + esc(u) + '" target="_blank" rel="noopener nofollow">' + esc(u) + '</a></li>';
					}).join('') + '</ul>';
				}
				return '<div class="azwc-check"><span class="azwc-dot" style="background:' + COLOR[c.status] + '"></span>'
					+ '<div><h4>' + esc(c.label) + '</h4><p>' + esc(c.detail) + '</p>'
					+ plainBlock(c) + list + '</div></div>';
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

		/**
		 * Everything that did not pass, worst first, with its URLs. This is the
		 * part a visitor actually works from - the per-check list above reads
		 * well but buries the actionable items among the passes.
		 */
		function actionsPanel(checks) {
			var rank = { fail: 0, warn: 1 };
			var todo = checks.filter(function (c) { return c.status === 'fail' || c.status === 'warn'; })
				.slice().sort(function (a, b) { return rank[a.status] - rank[b.status]; });

			if (!todo.length) {
				return '<section class="azwc-panel"><h3>What to fix first</h3>'
					+ '<p class="azwc-sub">Ordered by impact</p>'
					+ '<div class="azwc-allclear">Nothing failed and nothing was flagged for improvement. Every check on this page passed.</div>'
					+ '</section>';
			}

			var rows = todo.map(function (c) {
				var urls = '';
				if (c.items && c.items.length) {
					urls = '<ul class="azwc-items">' + c.items.map(function (u) {
						var safe = esc(u);
						return /^https?:/i.test(u)
							? '<li><a href="' + safe + '" target="_blank" rel="noopener nofollow">' + safe + '</a></li>'
							: '<li>' + safe + '</li>';
					}).join('') + '</ul>';
				}
				return '<div class="azwc-action"><span class="azwc-action-num"></span><div>'
					+ '<h4>' + esc(c.label)
					+ '<span class="azwc-sev ' + c.status + '">'
					+ (c.status === 'fail' ? 'Needs fixing' : 'Worth improving') + '</span></h4>'
					+ '<p>' + esc(c.detail) + '</p>' + plainBlock(c) + urls + '</div></div>';
			}).join('');

			var fails = todo.filter(function (c) { return c.status === 'fail'; }).length;
			return '<section class="azwc-panel"><h3>What to fix first</h3>'
				+ '<p class="azwc-sub">' + todo.length + ' item' + (todo.length === 1 ? '' : 's')
				+ ' to look at, ordered by impact'
				+ (fails ? ' \u2014 the first ' + fails + ' can stop a page ranking' : '') + '</p>'
				+ '<div class="azwc-actions">' + rows + '</div></section>';
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

			// Speed arrives after the site checks, so say so rather than letting a
			// half-finished report look final.
			var psi = d.psi || {};
			var speedDone = (psi.mobile !== undefined ? 1 : 0) + (psi.desktop !== undefined ? 1 : 0);
			var waiting = speedDone < 2;
			// Stages: fetch + checks are both satisfied by the first response, so
			// two of four are already done by the time anything renders.
			var pct = Math.round(((2 + speedDone) / 4) * 100);
			var pending = waiting
				? '<div class="azwc-pending">'
					+ '<span class="azwc-pending-head"><i></i><span>Still measuring speed with Google&rsquo;s API'
					+ ' &mdash; check ' + (speedDone + 1) + ' of 2. Everything else below is complete, and the speed'
					+ ' section fills in by itself.</span></span>'
					+ '<span class="azwc-pending-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"'
					+ ' aria-valuenow="' + pct + '" aria-label="Audit progress">'
					+ '<span style="width:' + pct + '%"></span></span>'
					+ '</div>'
				: '';

			var html = pending + '<section class="azwc-panel"><h3>' + esc(d.url) + '</h3>'
				+ '<p class="azwc-sub">Checked ' + new Date(d.fetched).toLocaleString()
				+ ' &middot; server responded in ' + esc(d.ms) + ' ms'
				+ (d.cached ? ' &middot; cached result' : '') + '</p>'
				+ '<div class="azwc-top">'
				+ gauge(d.score.overall).replace('across </figcaption>', 'across ' + scored + ' checks</figcaption>')
				+ '<div>' + bars(d.score.groups)
				+ '<p class="azwc-sub" style="margin:16px 0 0">'
				+ counts.fail + ' need fixing, ' + counts.warn + ' worth improving, ' + counts.pass + ' passing.'
				+ '</p>'
				+ '<div class="azwc-stats">'
				+ '<div class="azwc-stat"><b>' + scored + '</b><span>Checks run</span></div>'
				+ '<div class="azwc-stat"><b style="color:' + COLOR.fail + '">' + counts.fail + '</b><span>Need fixing</span></div>'
				+ '<div class="azwc-stat"><b style="color:' + COLOR.warn + '">' + counts.warn + '</b><span>Worth improving</span></div>'
				+ '<div class="azwc-stat"><b style="color:' + COLOR.pass + '">' + counts.pass + '</b><span>Passing</span></div>'
				+ '</div>'
				+ '</div></div></section>';

			html += checksPanel(d.checks);
			html += psiPanel(d.psi);
			html += actionsPanel(d.checks);
			html += authorityPanel(d.authority);
			// Filled in by the follow-up plugin (PDF delivery, call booking).
			// The plain block below is what shows if that plugin is not loaded,
			// so the report never ends with nothing to do next.
			html += '<section class="azwc-cta" id="azwc-followup-cta"><h3>Want these fixed?</h3>'
				+ '<p>We are in Gilbert. Call (480) 818-5761 or email info@azwebcorp.com and we will walk through this report with you — no charge, no obligation.</p>'
				+ '<a href="tel:+14808185761">Call (480) 818-5761</a></section>';

			out.innerHTML = html;
			out.hidden = false;

			/**
			 * Tell anything listening that a finished report is on screen.
			 *
			 * render() is called once, after every stage has settled, so this
			 * fires exactly once per run and always describes a complete report.
			 * `done`/`TOTAL` are locals of run() and deliberately not consulted
			 * here - they are not in scope, and a try/catch would have hidden
			 * that by silently never dispatching.
			 */
			if (typeof CustomEvent === 'function') {
				root.dispatchEvent(new CustomEvent('azwc:rendered', {
					bubbles: true,
					detail: {
						domain: (d && d.url) || '',
						score: (d && d.score && d.score.overall) || null
					}
				}));
			}
			// render() runs again as each speed stage returns. Scrolling every
			// time would yank the page out from under someone already reading.
			if (!out.dataset.scrolled) {
				out.dataset.scrolled = '1';
				var azwcTop = out.getBoundingClientRect().top + window.pageYOffset - 120;
					window.scrollTo({ top: azwcTop > 0 ? azwcTop : 0, behavior: 'smooth' });
			}
		}

		/* ---- staged runner -------------------------------------------------
		   Each entry is one real request. The tick appears when that request
		   returns, so the progress reflects work in flight rather than a timer.
		   Nothing here pads the clock — a fast stage reports fast.            */
		var STEPS = [
			{ key: 'site', label: 'Fetching the page', note: 'Following redirects, then reading robots.txt and the sitemap' },
			{ key: 'checks', label: 'Checking indexability, structure and markup', note: '' },
			{ key: 'psi_mobile', label: 'Asking Google for mobile speed data', note: 'PageSpeed Insights — this is the slow one, up to a minute' },
			{ key: 'psi_desktop', label: 'Asking Google for desktop speed data', note: '' }
		];

		var stepEls = {};
		// Incremented per run; a late PSI response from an older run is ignored.
		var azwcRunToken = 0;

		function buildSteps() {
			var ul = progress.querySelector('.azwc-steps');
			ul.innerHTML = '';
			stepEls = {};
			STEPS.forEach(function (s) {
				var li = document.createElement('li');
				li.className = 'azwc-step';
				li.innerHTML = '<span class="azwc-mark"></span><span>' + esc(s.label)
					+ (s.note ? '<small>' + esc(s.note) + '</small>' : '') + '</span>';
				ul.appendChild(li);
				stepEls[s.key] = li;
			});
			progress.hidden = false;
		}

		/** Show which URL a step is actually working on. */
		function setTarget(key, text) {
			var el = stepEls[key];
			if (!el || !text) { return; }
			var wrap = el.querySelector('span:last-child');
			if (!wrap) { return; }
			var t = wrap.querySelector('small.azwc-target');
			if (!t) {
				t = document.createElement('small');
				t.className = 'azwc-target';
				wrap.appendChild(t);
			}
			t.textContent = text;
		}

		var azwcTimer = null;
		var scanEl  = root.querySelector('.azwc-scan');
		var tilesEl = root.querySelector('.azwc-tiles');

		function tile(cls) {
			var el = document.createElement('span');
			el.className = 'azwc-tile ' + (cls || 'is-pending');
			return el;
		}

		/** Placeholder grid while the page is being fetched. */
		function scanStart(target, guess) {
			if (!scanEl || !tilesEl) { return; }
			tilesEl.innerHTML = '';
			for (var i = 0; i < guess; i++) { tilesEl.appendChild(tile()); }
			var t = root.querySelector('.azwc-scan-target');
			if (t) { t.textContent = target; }
			scanCount(0, guess);
			scanEl.hidden = false;
		}

		function scanCount(done, total) {
			var c = root.querySelector('.azwc-scan-count');
			if (c) { c.textContent = done + ' of ' + total + ' checks complete'; }
		}

		/**
		 * Reveal the real results.
		 *
		 * All 28 site checks arrive in one response, so this is a staggered
		 * REVEAL of results already in hand - not a fake progress animation.
		 * Kept fast (38ms a tile) so it reads as the grid filling in rather
		 * than pretending the work is still happening.
		 */
		function scanFill(checks, extraPending) {
			if (!tilesEl) { return; }
			var total = checks.length + extraPending;
			while (tilesEl.children.length < total) { tilesEl.appendChild(tile()); }
			while (tilesEl.children.length > total) { tilesEl.removeChild(tilesEl.lastChild); }
			checks.forEach(function (c, i) {
				var el = tilesEl.children[i];
				window.setTimeout(function () {
					el.className = 'azwc-tile is-' + c.status;
					el.setAttribute('title', c.label);
					scanCount(i + 1, total);
				}, i * 38);
			});
		}

		/** Resolve one of the trailing speed tiles. */
		function scanSpeed(index, ok, label) {
			if (!tilesEl) { return; }
			var el = tilesEl.children[tilesEl.children.length - 2 + index];
			if (!el) { return; }
			el.className = 'azwc-tile ' + (ok ? 'is-pass' : 'is-info');
			el.setAttribute('title', label);
		}


		/** Advance the bar to the share of stages that have reported. */
		function setProgress(done, total, running) {
			var bar = progress.querySelector('.azwc-bar');
			var fill = progress.querySelector('.azwc-bar-fill');
			if (!bar || !fill) { return; }
			var pct = total ? Math.round((done / total) * 100) : 0;
			fill.style.width = pct + '%';
			bar.setAttribute('aria-valuenow', String(pct));
			bar.classList.toggle('is-running', !!running);
		}

		function startTimer(t0) {
			var el = progress.querySelector('.azwc-elapsed');
			stopTimer();
			if (!el) { return; }
			azwcTimer = window.setInterval(function () {
				el.textContent = ((Date.now() - t0) / 1000).toFixed(1) + 's';
			}, 200);
		}
		function stopTimer() {
			if (azwcTimer) { window.clearInterval(azwcTimer); azwcTimer = null; }
		}

		function mark(key, state, note) {
			var el = stepEls[key];
			if (!el) { return; }
			el.className = 'azwc-step ' + state;
			if (note) {
				var small = el.querySelector('small');
				if (!small) {
					small = document.createElement('small');
					el.querySelector('span:last-child').appendChild(small);
				}
				small.textContent = note;
			}
		}

		function post(body) {
			return fetch(root.dataset.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(body)
			}).then(function (r) {
				return r.json().then(function (j) { return { ok: r.ok, body: j }; });
			});
		}

		function fail(message) {
			stopTimer();
			progress.hidden = true;
			statusEl.hidden = false;
			statusEl.className = 'azwc-audit-status error';
			statusEl.textContent = message;
			button.disabled = false;
		}

		function run(domain) {
			var t0 = Date.now();
			var data = null;
			var myRun = ++azwcRunToken;
			var TOTAL = 4;   // fetch, checks, speed x2
			var done  = 0;

			buildSteps();
			setTarget('site', domain);
			// 28 is what the current check set produces; the grid reconciles to the
			// real count as soon as the response arrives, so a drift here is cosmetic.
			scanStart(domain, 28);
			setProgress(0, TOTAL, true);
			startTimer(t0);
            mark('site', 'active');

			post({ domain: domain, stage: 'site' })
				.then(function (res) {
					if (!res.ok) {
						throw new Error(res.body && res.body.error ? res.body.error : 'The audit could not run.');
					}
					data = res.body;
					data.psi = {}; // leave keys undefined so the panel shows "waiting", not "no data", until Google actually answers

					mark('site', 'done', 'HTTP ' + data.status + ' in ' + data.ms + ' ms'
						+ (data.cached ? ' (cached)' : ''));
					setTarget('site', data.url);
					// Two trailing tiles are held for the speed strategies, which are
					// genuinely still running.
					scanFill(data.checks, 2);
					done = 2;   // fetch + checks both satisfied by this one response
					setProgress(done, TOTAL, true);
					mark('checks', 'done', data.checks.length + ' checks evaluated');
					var bad = data.checks.filter(function (c) { return c.status === 'fail' || c.status === 'warn'; }).length;
					setTarget('checks', bad + ' of ' + data.checks.length + ' need attention');

					// Deliberately no render here: the report is withheld until the
					// diagnosis is complete, so a visitor never sees a partial report
					// that looks finished. The progress panel carries the run.
					// The button stays disabled because the tool genuinely is busy.

					// Sequential on purpose. Google throttles concurrent requests for
					// the same URL: run together they measured 55s and 69s, against 37s
					// and 56s run one after the other, and the slower one was then cut
					// off by the ~60s proxy cap in front of this site. Chained is a
					// little slower overall but actually returns both scores.
					mark('psi_mobile', 'active');
					setTarget('psi_mobile', data.url);

					/**
					 * Ask for one strategy, retrying if it comes back empty.
					 *
					 * A dropped connection does not stop the server finishing the call
					 * (it sets ignore_user_abort), so the result usually lands in the
					 * cache a few seconds later and the retry returns it immediately.
					 */
					function speed(strategy, key) {

						function settle(psi) {
							if (myRun !== azwcRunToken) { return; }
							data.psi[key] = psi;
							mark('psi_' + key, psi ? 'done' : 'skip',
								psi ? (psi.score === null ? 'returned' : 'performance score ' + psi.score + '/100')
								    : 'Google did not return data');
							done += 1;
							setProgress(done, TOTAL, done < TOTAL);
							scanSpeed(key === 'mobile' ? 0 : 1, !!psi,
								(key === 'mobile' ? 'Mobile' : 'Desktop') + ' speed'
								+ (psi && psi.score !== null ? ' - ' + psi.score + '/100' : ' - no data'));
							// No render here - see the note above; the report is drawn once,
							// at the end, so nothing partial is ever shown.
						}

						/**
						 * Poll the cache rather than re-requesting.
						 *
						 * The first call may have had its connection cut by the proxy at
						 * ~60s, but the server keeps going and writes its result. A peek
						 * is free, so wait for that instead of paying for a second call.
						 */
						function pollForResult(tries) {
							if (myRun !== azwcRunToken) { return null; }
							if (tries <= 0) { settle(null); return null; }
							mark('psi_' + key, 'active', 'finishing up, waiting for Google to return');
							return new Promise(function (resolve) {
								window.setTimeout(function () {
									resolve(
										post({ domain: domain, stage: 'psi', strategy: strategy, peek: true })
											.then(function (r) {
												if (myRun !== azwcRunToken) { return; }
												var got = r.ok ? r.body.psi : null;
												if (got) { settle(got); return; }
												return pollForResult(tries - 1);
											})
											.catch(function () { return pollForResult(tries - 1); })
									);
								}, 4000);
							});
						}

						return post({ domain: domain, stage: 'psi', strategy: strategy })
							.then(function (res) {
								if (myRun !== azwcRunToken) { return; }
								var psi = res.ok ? res.body.psi : null;
								if (!psi) { return pollForResult(20); }   // ~80s of cheap polls
								settle(psi);
							})
							.catch(function () {
								if (myRun !== azwcRunToken) { return; }
								return pollForResult(20);
							});
					}

					return speed('mobile', 'mobile').then(function () {
						if (myRun !== azwcRunToken) { return; }
						mark('psi_desktop', 'active');
						setTarget('psi_desktop', data.url);
						return speed('desktop', 'desktop');
					});
				})
				.then(function () {
					if (myRun !== azwcRunToken) { return; }
					setProgress(TOTAL, TOTAL, false);
					stopTimer();
					var secs = ((Date.now() - t0) / 1000).toFixed(1);
					progress.querySelector('.azwc-sub').textContent = 'Finished in ' + secs + ' seconds.';

					// render only when the diagnosis is complete - one report, in one
					// piece, with every section already populated.
					render(data);
					button.disabled = false;
				})
				.catch(function (err) {
					if (myRun !== azwcRunToken) { return; }
					fail(err && err.message ? err.message : 'The audit could not complete. If the site is slow to respond it may have timed out — try again.');
				});
		}

		var azwcResultsUrl = root.dataset.results || '';
		var azwcTarget = (function () {
			try { return new URLSearchParams(window.location.search).get('target') || ''; }
			catch (e) { return ''; }
		})();

		/**
		 * Results page: the domain is already decided, so hide the input and go.
		 */
		if (azwcTarget) {
			root.classList.add('is-results');
			input.value = azwcTarget;
			window.setTimeout(function () {
				out.hidden = true;
				out.innerHTML = '';
				statusEl.hidden = true;
				button.disabled = true;
				run(azwcTarget);
			}, 60);
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var domain = input.value.trim();
			if (!domain) { return; }

			// From the audit page, hand off to the dedicated results tab. Opened
			// straight from this click so it is a user gesture and survives popup
			// blocking. On the results page itself azwcTarget is set, so we run
			// inline instead of spawning tabs forever.
			if (azwcResultsUrl && !azwcTarget) {
				window.open(azwcResultsUrl + '?target=' + encodeURIComponent(domain), '_blank', 'noopener');
				return;
			}
			out.hidden = true;
			out.innerHTML = '';
			delete out.dataset.scrolled;
			statusEl.hidden = true;
			button.disabled = true;
			run(domain);
		});
	})();
	</script>
	<?php
}
