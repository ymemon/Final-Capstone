<?php
/**
 * Knowledge base built from the site's own published pages.
 *
 * Rebuilt on demand (admin button / WP-Cron), stored as one option. Keeps the
 * assistant answering from what is actually published rather than from
 * anything invented — the same discipline applied to the hardware page.
 */

defined('ABSPATH') || exit;

class EIT_Chat_KB {

    const OPTION      = 'eit_chat_kb';
    const OPTION_TIME = 'eit_chat_kb_built';
    const PER_PAGE_WORDS = 160;

    /** Pages that are noise for a visitor-facing assistant. */
    const SKIP_SLUGS = ['eit-menu-styles', 'privacy-notice'];

    public static function get() {
        $kb = get_option(self::OPTION, []);
        return is_array($kb) ? $kb : [];
    }

    public static function built_at() {
        return get_option(self::OPTION_TIME, '');
    }

    /**
     * Scan published pages into a compact [slug => [title,url,text]] map.
     */
    public static function rebuild() {
        $pages = get_posts([
            'post_type'        => 'page',
            'post_status'      => 'publish',
            'numberposts'      => 300,
            'has_password'     => false,
            'suppress_filters' => true,
        ]);

        $kb = [];
        foreach ($pages as $p) {
            if (in_array($p->post_name, self::SKIP_SLUGS, true)) {
                continue;
            }
            $text = self::extract_text($p);
            if (str_word_count($text) < 25) {
                continue; // too thin to answer from
            }
            $kb[] = [
                'title' => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'),
                'url'   => get_permalink($p),
                'text'  => $text,
            ];
        }

        update_option(self::OPTION, $kb, false);
        update_option(self::OPTION_TIME, current_time('mysql'), false);
        return count($kb);
    }

    /**
     * Strip a page down to readable prose.
     *
     * These pages are single raw-HTML Elementor widgets carrying a big inline
     * <style> block, so the stylesheet has to come out before tag stripping —
     * otherwise every CSS rule lands in the knowledge base as "text".
     */
    private static function extract_text($post) {
        $html = $post->post_content;

        if (trim($html) === '' && function_exists('get_post_meta')) {
            $ed = get_post_meta($post->ID, '_elementor_data', true);
            if (is_string($ed) && $ed !== '') {
                $html = $ed;
            }
        }

        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
        $html = preg_replace('#<(br|/p|/h[1-6]|/li|/div)\s*/?>#i', ' ', $html);

        $text = wp_strip_all_tags($html, true);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > self::PER_PAGE_WORDS) {
            $words = array_slice($words, 0, self::PER_PAGE_WORDS);
            $text  = implode(' ', $words) . '…';
        } else {
            $text = implode(' ', $words);
        }
        return $text;
    }

    /* ── retrieval ───────────────────────────────────────────────────── */

    /** Words too common to signal relevance. */
    const STOP = ['the','and','for','you','your','are','can','with','that','this','have','how','what','does','our','from','all','any','get','has','was','who','why','when','they','their','into','about','would','could','need','want','please','hello','there'];

    /**
     * Score KB entries against a query by term overlap. Deliberately simple:
     * no embeddings, no external service, no per-query cost.
     */
    public static function search($query, $limit = 4) {
        $kb = self::get();
        if (!$kb) {
            return [];
        }

        $terms = self::terms($query);
        if (!$terms) {
            return [];
        }

        $scored = [];
        foreach ($kb as $i => $entry) {
            $hay   = strtolower($entry['title'] . ' ' . $entry['title'] . ' ' . $entry['text']);
            $score = 0;
            foreach ($terms as $t) {
                $n = substr_count($hay, $t);
                if ($n > 0) {
                    // Title hits already count double via the duplicated title above.
                    $score += min($n, 4) * (strlen($t) > 6 ? 2 : 1);
                }
            }
            if ($score > 0) {
                $scored[$i] = $score;
            }
        }

        arsort($scored);
        $out = [];
        foreach (array_slice($scored, 0, $limit, true) as $i => $score) {
            $out[] = $kb[$i] + ['score' => $score];
        }
        return $out;
    }

    private static function terms($query) {
        $q = strtolower((string) $query);
        $q = preg_replace('/[^a-z0-9\s\-]/', ' ', $q);
        $parts = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        $terms = [];
        foreach ($parts as $p) {
            if (strlen($p) < 3 || in_array($p, self::STOP, true)) {
                continue;
            }
            $terms[$p] = true;
        }
        return array_keys($terms);
    }
}
