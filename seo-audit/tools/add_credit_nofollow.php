<?php
/**
 * Add rel="nofollow" to the sitewide "Powered by AZWebCorp" credit link.
 *
 * WHY: the credit is repeated on every page of every client site and all point
 * at one domain. Left followed, that is the classic footprint of a sitewide
 * link scheme. nofollow keeps the branding and the referral clicks while
 * telling Google not to pass PageRank.
 *
 * SAFETY:
 *  - Only anchors whose tag contains azwebcorp.com are touched. Every other
 *    rel="noopener" on the site (and there are many) is left alone.
 *  - Handles both plain HTML and JSON-escaped (\") markup, since Elementor
 *    stores its HTML inside a JSON blob.
 *  - Anything already carrying nofollow is skipped, so re-running is safe.
 *  - For _elementor_data, the result must still json_decode to the same
 *    top-level element count or the row is left untouched.
 *  - Pass --apply to write. Default is a dry run.
 *
 * Usage:  wp eval-file add_credit_nofollow.php          (dry run)
 *         wp eval-file add_credit_nofollow.php apply    (writes)
 *
 * Positional, not --apply: WP-CLI consumes leading-dash arguments itself and
 * rejects unknown ones before the script ever runs.
 */

global $wpdb;

$apply = in_array( 'apply', (array) ( $args ?? [] ), true );

echo $apply ? "MODE: APPLY (writing changes)\n\n" : "MODE: DRY RUN (no changes written)\n\n";

/** Add nofollow to azwebcorp anchors inside an arbitrary blob of markup. */
function azw_add_nofollow( $raw, &$hits ) {
	$hits = 0;
	return preg_replace_callback(
		'#<a\b[^>]*>#i',
		function ( $m ) use ( &$hits ) {
			$tag = $m[0];
			if ( stripos( $tag, 'azwebcorp.com' ) === false ) {
				return $tag;               // not our credit link
			}
			if ( stripos( $tag, 'nofollow' ) !== false ) {
				return $tag;               // already done
			}
			// JSON-escaped form first (Elementor), then plain HTML.
			if ( strpos( $tag, 'rel=\\"noopener\\"' ) !== false ) {
				$hits++;
				return str_replace( 'rel=\\"noopener\\"', 'rel=\\"noopener nofollow\\"', $tag );
			}
			if ( strpos( $tag, 'rel="noopener"' ) !== false ) {
				$hits++;
				return str_replace( 'rel="noopener"', 'rel="noopener nofollow"', $tag );
			}

			/*
			 * No rel attribute at all (seen on PaloVerde). Add the whole thing.
			 * These also carry target="_blank" with no noopener, so adding it
			 * closes the reverse-tabnabbing gap at the same time.
			 * Quote style has to match the surrounding context: inside an
			 * Elementor JSON blob the markup uses \" and emitting a bare "
			 * would break the JSON.
			 */
			if ( ! preg_match( '/\brel\s*=/i', $tag ) ) {
				$escaped = ( strpos( $tag, '\\"' ) !== false );
				$q       = $escaped ? '\\"' : '"';
				$new     = preg_replace( '/\s*>$/', ' rel=' . $q . 'noopener nofollow' . $q . '>', $tag, 1 );
				if ( $new && $new !== $tag ) {
					$hits++;
					return $new;
				}
			}

			echo "    ! anchor with azwebcorp.com but unrecognised rel, left alone:\n      "
				. substr( preg_replace( '/\s+/', ' ', $tag ), 0, 160 ) . "\n";
			return $tag;
		},
		$raw
	);
}

$total = 0;

/* ---------- postmeta (_elementor_data and friends) ----------
 * Revisions are excluded on purpose: they are historical snapshots, so
 * rewriting them edits the site's own history for no rendering benefit and
 * makes the change log noisy. Everything IT alone had 20+ footer revisions.
 */
$rows = $wpdb->get_results(
	"SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value
	   FROM {$wpdb->postmeta} pm
	   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	  WHERE pm.meta_value LIKE '%azwebcorp.com%'
	    AND p.post_type != 'revision'"
);

foreach ( $rows as $r ) {
	$new = azw_add_nofollow( $r->meta_value, $hits );
	if ( ! $hits ) { continue; }

	if ( '_elementor_data' === $r->meta_key ) {
		$before = json_decode( $r->meta_value, true );
		$after  = json_decode( $new, true );
		if ( null === $after || ! is_array( $before ) || count( $before ) !== count( $after ) ) {
			echo "  SKIP post {$r->post_id} {$r->meta_key}: JSON validation failed\n";
			continue;
		}
	}

	echo "  post {$r->post_id}  {$r->meta_key}  ({$hits} link" . ( $hits > 1 ? 's' : '' ) . ")\n";
	$total += $hits;

	if ( $apply ) {
		$wpdb->update( $wpdb->postmeta, [ 'meta_value' => $new ], [ 'meta_id' => $r->meta_id ] );
		clean_post_cache( $r->post_id );
	}
}

/* ---------- post_content (classic content, Elementor library templates) ---------- */
$posts = $wpdb->get_results(
	"SELECT ID, post_type, post_title, post_content
	   FROM {$wpdb->posts}
	  WHERE post_content LIKE '%azwebcorp.com%'
	    AND post_type != 'revision'"
);

foreach ( $posts as $p ) {
	$new = azw_add_nofollow( $p->post_content, $hits );
	if ( ! $hits ) { continue; }

	echo "  post {$p->ID} ({$p->post_type}) post_content  ({$hits} link" . ( $hits > 1 ? 's' : '' ) . ")\n";
	$total += $hits;

	if ( $apply ) {
		// Direct write: wp_update_post runs content filters that have stripped
		// markup on these hosts before.
		$wpdb->update( $wpdb->posts, [ 'post_content' => $new ], [ 'ID' => $p->ID ] );
		clean_post_cache( $p->ID );
	}
}

/* ---------- options (widgets, theme settings) ---------- */
$opts = $wpdb->get_results(
	"SELECT option_name, option_value
	   FROM {$wpdb->options}
	  WHERE option_value LIKE '%azwebcorp.com%'"
);

foreach ( $opts as $o ) {
	// Serialized values carry byte-length prefixes; changing the string without
	// re-serializing corrupts them. Skip and report rather than risk it.
	$is_serialized = is_serialized( $o->option_value );
	$new = azw_add_nofollow( $o->option_value, $hits );
	if ( ! $hits ) { continue; }

	if ( $is_serialized ) {
		echo "  option {$o->option_name}: {$hits} link(s) found but value is SERIALIZED - handled separately below\n";
		$un = maybe_unserialize( $o->option_value );
		$un = azw_deep_nofollow( $un, $h2 );
		if ( $h2 ) {
			echo "    -> {$h2} link(s) via safe unserialize/reserialize\n";
			$total += $h2;
			if ( $apply ) { update_option( $o->option_name, $un ); }
		}
		continue;
	}

	echo "  option {$o->option_name}  ({$hits} link" . ( $hits > 1 ? 's' : '' ) . ")\n";
	$total += $hits;
	if ( $apply ) { update_option( $o->option_name, $new ); }
}

echo "\nTotal credit links " . ( $apply ? 'updated' : 'that would be updated' ) . ": {$total}\n";

/** Walk a serialized structure and patch every string inside it. */
function azw_deep_nofollow( $val, &$hits ) {
	$hits = 0;
	$walk = function ( $v ) use ( &$walk, &$hits ) {
		if ( is_string( $v ) ) {
			$n = azw_add_nofollow( $v, $h );
			$hits += $h;
			return $n;
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $vv ) { $v[ $k ] = $walk( $vv ); }
			return $v;
		}
		if ( is_object( $v ) ) {
			foreach ( get_object_vars( $v ) as $k => $vv ) { $v->$k = $walk( $vv ); }
			return $v;
		}
		return $v;
	};
	return $walk( $val );
}
