<?php
/**
 * PDF report and calendar invite.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load dompdf if this install has one.
 *
 * dompdf is not ours — it arrives vendored inside GoDaddy's platform plugin,
 * which is auto-updated and could move or drop it without warning. So this
 * never assumes: it probes, caches the answer for the request, and callers
 * are expected to handle false by sending the HTML edition instead. A missing
 * library must degrade the attachment, never fail the lead.
 */
function azwc_fu_dompdf_ready() {
	static $ready = null;
	if ( null !== $ready ) {
		return $ready;
	}

	if ( class_exists( '\Dompdf\Dompdf' ) ) {
		$ready = true;
		return $ready;
	}

	$candidates = array(
		WPMU_PLUGIN_DIR . '/vendor/godaddy/mwc-core/vendor/autoload.php',
		WPMU_PLUGIN_DIR . '/vendor/autoload.php',
		WP_PLUGIN_DIR . '/woocommerce/vendor/autoload.php',
	);

	foreach ( $candidates as $file ) {
		if ( is_readable( $file ) ) {
			require_once $file;
			if ( class_exists( '\Dompdf\Dompdf' ) ) {
				$ready = true;
				return $ready;
			}
		}
	}

	$ready = false;
	return $ready;
}

function azwc_fu_status_colour( $status ) {
	$map = array(
		'pass' => '#0f9d58',
		'warn' => '#c8871f',
		'fail' => '#d64545',
		'info' => '#6b7480',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : '#6b7480';
}

function azwc_fu_status_word( $status ) {
	$map = array(
		'pass' => 'Good',
		'warn' => 'Improve',
		'fail' => 'Fix',
		'info' => 'Note',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : 'Note';
}

/**
 * The report as standalone HTML.
 *
 * Also the fallback attachment when dompdf is unavailable, which is why it
 * carries its own styling and no external references of any kind.
 */
function azwc_fu_report_html( $report, $name = '' ) {
	$site   = $report['site'];
	$checks = $site['checks'];
	$score  = isset( $site['score']['overall'] ) ? $site['score']['overall'] : null;
	$host   = wp_parse_url( $report['url'], PHP_URL_HOST );

	$todo = array_values(
		array_filter(
			$checks,
			function ( $c ) {
				return 'fail' === $c['status'] || 'warn' === $c['status'];
			}
		)
	);
	usort(
		$todo,
		function ( $a, $b ) {
			$rank = array( 'fail' => 0, 'warn' => 1 );
			return $rank[ $a['status'] ] - $rank[ $b['status'] ];
		}
	);

	$e = 'esc_html';

	ob_start();
	?>
<style>
	@page { margin: 34mm 16mm 20mm; }
	body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.55; color: #1c2129; }
	h1, h2, h3, h4 { margin: 0; font-weight: bold; }
	.wordmark { font-size: 15pt; letter-spacing: .16em; color: #9b711b; }
	.cover { border-bottom: 3px solid #e6b84d; padding-bottom: 14px; margin-bottom: 20px; }
	.cover h1 { font-size: 21pt; margin: 10px 0 4px; }
	.cover .dom { font-size: 12pt; color: #444c57; }
	.cover .when { font-size: 8.5pt; color: #6b7480; margin-top: 6px; }
	.scorebox { background: #f6f7f9; border: 1px solid #e2e5ea; border-radius: 7px; padding: 14px 16px; margin-bottom: 18px; }
	.scorebox .big { font-size: 30pt; font-weight: bold; color: #1c2129; }
	.scorebox .of { font-size: 11pt; color: #6b7480; }
	table { width: 100%; border-collapse: collapse; }
	td, th { text-align: left; vertical-align: top; }
	.grp td { padding: 3px 10px 3px 0; font-size: 9pt; }
	.grp .v { font-weight: bold; }
	h2.sec { font-size: 13pt; margin: 22px 0 3px; color: #1c2129; }
	p.sub { margin: 0 0 10px; font-size: 8.5pt; color: #6b7480; }
	.item { border: 1px solid #e2e5ea; border-left-width: 4px; border-radius: 6px; padding: 9px 12px; margin-bottom: 8px; page-break-inside: avoid; }
	.item h4 { font-size: 10.5pt; margin-bottom: 3px; }
	.pill { font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: .07em; color: #fff; padding: 1px 6px; border-radius: 8px; }
	.det { margin: 3px 0 0; font-size: 9pt; color: #333b45; }
	.plain { margin: 6px 0 0; padding: 7px 9px; background: #faf6ec; border-left: 3px solid #e6b84d; font-size: 8.7pt; color: #4a5260; }
	.plain b { display: block; font-size: 7pt; letter-spacing: .08em; text-transform: uppercase; color: #9b711b; }
	ul.urls { margin: 6px 0 0; padding-left: 15px; font-size: 8pt; color: #5a626d; }
	ul.urls li { margin-bottom: 1px; word-wrap: break-word; }
	.cta { margin-top: 24px; background: #12161d; color: #ffffff; border-radius: 8px; padding: 16px 18px; page-break-inside: avoid; }
	.cta h3 { color: #e6b84d; font-size: 12pt; margin-bottom: 5px; }
	.cta p { margin: 0 0 4px; font-size: 9pt; color: #d6dce4; }
	.foot { margin-top: 16px; border-top: 1px solid #e2e5ea; padding-top: 8px; font-size: 7.5pt; color: #8a919b; }
</style>

<div class="cover">
	<div class="wordmark">AZ WEB CORP</div>
	<h1>SEO Audit Report</h1>
	<div class="dom"><?php echo $e( $host ); ?></div>
	<div class="when">
		Prepared <?php echo $e( azwc_fu_local( gmdate( 'Y-m-d H:i:s' ) )->format( 'j F Y, g:ia' ) ); ?> Arizona time
		<?php if ( $name ) : ?>&nbsp;·&nbsp;for <?php echo $e( $name ); ?><?php endif; ?>
	</div>
</div>

<div class="scorebox">
	<table><tr>
		<td style="width:26%">
			<span class="big"><?php echo null === $score ? '—' : (int) $score; ?></span><span class="of">/100</span>
		</td>
		<td>
			<table class="grp">
			<?php
			$labels = array(
				'technical'  => 'Technical',
				'onpage'     => 'On-page',
				'structured' => 'Structured data',
			);
			foreach ( $labels as $key => $label ) :
				if ( ! isset( $site['score']['groups'][ $key ] ) ) {
					continue;
				}
				?>
				<tr>
					<td style="width:120px"><?php echo $e( $label ); ?></td>
					<td class="v"><?php echo (int) $site['score']['groups'][ $key ]; ?>/100</td>
				</tr>
			<?php endforeach; ?>
			</table>
		</td>
	</tr></table>
</div>

<?php if ( $report['mobile'] || $report['desktop'] ) : ?>
	<h2 class="sec">Speed</h2>
	<p class="sub">Measured by Google PageSpeed Insights against this page.</p>
	<table class="grp">
		<?php
		foreach ( array( 'mobile' => 'Mobile', 'desktop' => 'Desktop' ) as $k => $label ) :
			if ( empty( $report[ $k ] ) ) {
				continue;
			}
			$psi = $report[ $k ];
			?>
			<tr>
				<td style="width:120px"><?php echo $e( $label ); ?></td>
				<td class="v"><?php echo null === $psi['score'] ? 'no data' : (int) $psi['score'] . '/100'; ?></td>
				<td style="color:#6b7480">
					<?php
					$bits = array();
					foreach ( array( 'lcp' => 'LCP', 'cls' => 'CLS' ) as $mk => $ml ) {
						if ( ! empty( $psi['lab'][ $mk ]['display'] ) ) {
							$bits[] = $ml . ' ' . $psi['lab'][ $mk ]['display'];
						}
					}
					echo $e( implode( '  ·  ', $bits ) );
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>

<h2 class="sec">What to fix first</h2>
<?php if ( ! $todo ) : ?>
	<p class="sub">Nothing failed and nothing was flagged. Every check on this page passed.</p>
<?php else : ?>
	<p class="sub"><?php echo count( $todo ); ?> item<?php echo 1 === count( $todo ) ? '' : 's'; ?>, ordered by impact.</p>
	<?php foreach ( $todo as $i => $c ) : ?>
		<div class="item" style="border-left-color:<?php echo $e( azwc_fu_status_colour( $c['status'] ) ); ?>">
			<h4><?php echo (int) ( $i + 1 ); ?>. <?php echo $e( $c['label'] ); ?>
				<span class="pill" style="background:<?php echo $e( azwc_fu_status_colour( $c['status'] ) ); ?>"><?php echo $e( azwc_fu_status_word( $c['status'] ) ); ?></span>
			</h4>
			<p class="det"><?php echo $e( $c['detail'] ); ?></p>
			<?php if ( ! empty( $c['plain'] ) ) : ?>
				<div class="plain"><b>What this means</b><?php echo $e( $c['plain'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $c['items'] ) ) : ?>
				<ul class="urls">
					<?php foreach ( array_slice( $c['items'], 0, 12 ) as $u ) : ?>
						<li><?php echo $e( $u ); ?></li>
					<?php endforeach; ?>
					<?php if ( count( $c['items'] ) > 12 ) : ?>
						<li>… and <?php echo count( $c['items'] ) - 12; ?> more</li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<h2 class="sec">Everything we checked</h2>
<p class="sub">All <?php echo count( $checks ); ?> checks, including the ones that passed.</p>
<?php foreach ( $checks as $c ) : ?>
	<div class="item" style="border-left-color:<?php echo $e( azwc_fu_status_colour( $c['status'] ) ); ?>">
		<h4><?php echo $e( $c['label'] ); ?>
			<span class="pill" style="background:<?php echo $e( azwc_fu_status_colour( $c['status'] ) ); ?>"><?php echo $e( azwc_fu_status_word( $c['status'] ) ); ?></span>
		</h4>
		<p class="det"><?php echo $e( $c['detail'] ); ?></p>
	</div>
<?php endforeach; ?>

<div class="cta">
	<h3>Want this walked through?</h3>
	<p>This report is automated — it reads what your page sends to a browser. A person reading your site alongside your competitors will find things no crawler can.</p>
	<p>Book a free 30-minute call and we will run a hand audit before we speak, then talk you through it.</p>
	<p style="margin-top:8px"><b style="color:#e6b84d">623-670-1611</b> &nbsp;·&nbsp; info@azwebcorp.com &nbsp;·&nbsp; azwebcorp.com/free-seo-audit/</p>
</div>

<div class="foot">
	Every finding in this report was observed in the live response from
	<?php echo $e( $host ); ?> at the time shown. Nothing here is estimated.
	Backlink and keyword figures are deliberately absent: they cannot be measured
	by reading a page, and we do not print numbers we cannot stand behind.
</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the report to PDF bytes, or WP_Error if this box cannot.
 */
function azwc_fu_report_pdf( $report, $name = '' ) {
	if ( ! azwc_fu_dompdf_ready() ) {
		return new WP_Error( 'azwc_fu_no_pdf', 'No PDF renderer is available on this server.' );
	}

	try {
		$options = new \Dompdf\Options();
		$options->set( 'isRemoteEnabled', false );  // Never let report content fetch anything.
		$options->set( 'isHtml5ParserEnabled', true );
		// DejaVu ships with dompdf and covers the punctuation the copy uses;
		// the core PDF fonts would drop dashes and curly quotes.
		$options->set( 'defaultFont', 'DejaVu Sans' );

		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->setPaper( 'letter', 'portrait' );
		$dompdf->loadHtml( azwc_fu_report_html( $report, $name ), 'UTF-8' );
		$dompdf->render();

		$canvas = $dompdf->getCanvas();
		$canvas->page_text(
			488, 760, 'Page {PAGE_NUM} of {PAGE_COUNT}',
			null, 8, array( 0.54, 0.57, 0.61 )
		);

		$out = $dompdf->output();
	} catch ( \Throwable $e ) {
		return new WP_Error( 'azwc_fu_pdf_failed', $e->getMessage() );
	}

	if ( ! $out || '%PDF-' !== substr( $out, 0, 5 ) ) {
		return new WP_Error( 'azwc_fu_pdf_invalid', 'The renderer returned something that is not a PDF.' );
	}

	return $out;
}

/**
 * Write an attachment to a temp file.
 *
 * wp_mail only takes paths, so bytes have to land on disk. Kept outside the
 * uploads tree — these are one-shot files and nothing should be able to fetch
 * them over HTTP while they exist.
 */
function azwc_fu_tempfile( $bytes, $filename ) {
	$dir = get_temp_dir() . 'azwc-fu';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$path = trailingslashit( $dir ) . wp_unique_filename( $dir, $filename );
	if ( false === file_put_contents( $path, $bytes ) ) { // phpcs:ignore
		return new WP_Error( 'azwc_fu_tmp', 'Could not write the attachment.' );
	}

	return $path;
}


/* -------------------------------------------------------------------------
 * Calendar invite
 * ---------------------------------------------------------------------- */

function azwc_fu_ics_escape( $text ) {
	return str_replace(
		array( '\\', ';', ',', "\r\n", "\n" ),
		array( '\\\\', '\;', '\,', '\n', '\n' ),
		$text
	);
}

/**
 * Fold a content line to 75 octets, per RFC 5545.
 *
 * Not cosmetic: a long unfolded DESCRIPTION is rejected outright by some
 * clients (Outlook among them), which turns a booking confirmation into an
 * email with a broken attachment. Folding counts BYTES, not characters, and
 * must not split a multi-byte sequence — hence the mb_strcut.
 */
function azwc_fu_ics_fold( $line ) {
	if ( strlen( $line ) <= 75 ) {
		return $line;
	}

	$out  = mb_strcut( $line, 0, 75, 'UTF-8' );
	$rest = substr( $line, strlen( $out ) );

	while ( '' !== $rest ) {
		// 74 to leave room for the leading space that marks a continuation.
		$chunk = mb_strcut( $rest, 0, 74, 'UTF-8' );
		$out   .= "\r\n " . $chunk;
		$rest   = substr( $rest, strlen( $chunk ) );
	}

	return $out;
}

/**
 * A real .ics so the call lands in their calendar with one click.
 *
 * Times go out in UTC (the trailing Z) rather than as a floating local time,
 * so the event is correct in whatever timezone the recipient's calendar is
 * set to — which is the whole point, given we are booking across states.
 */
function azwc_fu_ics( $row ) {
	$start = new DateTimeImmutable( $row->slot_start_gmt, new DateTimeZone( 'UTC' ) );
	$end   = $start->modify( '+' . AZWC_FU_CALL_MIN . ' minutes' );
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );

	// Real newlines, not literal backslash-n: the escaper below is what turns
	// these into the \n the format wants. Writing them pre-escaped made it
	// double them to \\n, which clients render as visible backslashes.
	$desc = sprintf(
		"A free 30-minute SEO consultation for %s.\n\nWe will run a hand audit of your site before we call, then talk you through what we found and what we would do first.\n\nNeed to cancel? %s",
		$row->domain,
		azwc_fu_action_url( 'cancel', $row->token )
	);

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//AZ Web Corp//SEO Audit//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:REQUEST',
		'BEGIN:VEVENT',
		'UID:azwc-' . $row->token . '@' . $host,
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		'DTSTART:' . $start->format( 'Ymd\THis\Z' ),
		'DTEND:' . $end->format( 'Ymd\THis\Z' ),
		'SUMMARY:' . azwc_fu_ics_escape( 'SEO consultation with AZ Web Corp' ),
		'DESCRIPTION:' . azwc_fu_ics_escape( $desc ),
		'LOCATION:' . azwc_fu_ics_escape( 'By phone - we will call you' ),
		'ORGANIZER;CN=AZ Web Corp:mailto:' . azwc_fu_notify_email(),
		'ATTENDEE;CN=' . azwc_fu_ics_escape( $row->name ) . ';RSVP=FALSE:mailto:' . $row->email,
		'STATUS:CONFIRMED',
		'BEGIN:VALARM',
		'TRIGGER:-PT60M',
		'ACTION:DISPLAY',
		'DESCRIPTION:SEO consultation in one hour',
		'END:VALARM',
		'END:VEVENT',
		'END:VCALENDAR',
	);

	return implode( "\r\n", array_map( 'azwc_fu_ics_fold', $lines ) ) . "\r\n";
}
