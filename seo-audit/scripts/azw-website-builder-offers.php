<?php
/**
 * /website-builder/ — publish the real plan pricing, in text and in schema.
 *
 * Usage (from the site root):
 *   wp eval-file azw-website-builder-offers.php          # dry run
 *   wp eval-file azw-website-builder-offers.php apply
 *
 * WHY BOTH, AND IN THAT ORDER
 * The page carried a Product node with no `offers` and no price anywhere in the
 * body. Adding the offers alone would have been the wrong fix: Google's
 * structured-data policy requires marked-up prices to be visible to the reader,
 * so schema-only pricing is a guideline breach on a commercial page, not a
 * clever shortcut. So this adds a visible "Plans and pricing" section using the
 * page's own <h2>/<ul> markup, and mirrors exactly those numbers into the
 * Product node.
 *
 * Prices are read off the live storefront listings (Website Builder Personal
 * $4.99, Business $6.99, Business Plus $10.99, Online Store $19.99, all per
 * month) rather than recalled. If the storefront changes, this script is the
 * one place to update and re-run.
 *
 * The original post_content is written to a timestamped file in the home
 * directory before anything is saved.
 */

$APPLY = in_array('apply', $args ?? [], true);
$POST_ID = 2291;                       // /website-builder/

$PLANS = [
    ['Personal',      '4.99',  'Share your passion online. Responsive mobile design, website hosting, fast page loads, blog.'],
    ['Business',      '6.99',  'Create an online presence for your business. Responsive mobile design, website hosting, fast page loads.'],
    ['Business Plus', '10.99', 'Attract more customers. Adds a blog and security to the Business plan.'],
    ['Online Store',  '19.99', 'Sell products and services online, with hosting, responsive design and a blog.'],
];

$post = get_post($POST_ID);
if (!$post) {
    echo "post {$POST_ID} not found\n";
    return;
}
$content = $post->post_content;

/* ---------------------------------------------------------------- visible ---
 * Inserted before the FAQ heading so pricing reads before the objections, and
 * skipped entirely if a pricing section is already present - this must be safe
 * to re-run.
 */
$already_visible = (strpos($content, 'Plans and pricing') !== false);

$rows = '';
foreach ($PLANS as [$name, $price, $desc]) {
    $rows .= sprintf("<li><strong>Website Builder %s — $%s/month.</strong> %s</li>\n",
                     esc_html($name), esc_html($price), esc_html($desc));
}
$section = "<h2>Plans and pricing</h2>\n"
         . "<p>Website Builder plans are billed monthly. Prices shown are the current "
         . "storefront rates; taxes, promotions and final checkout amounts can vary, so "
         . "checkout remains authoritative.</p>\n"
         . "<ul>\n" . $rows . "</ul>\n"
         . "<p>Not sure whether the builder or a custom build fits? "
         . "<a href=\"https://azwebcorp.com/contact-us/\">Talk to our Gilbert, AZ team</a>.</p>\n";

$new_content = $content;
if (!$already_visible) {
    $anchor = '<h2>FAQ';
    $pos = strpos($new_content, $anchor);
    if ($pos === false) {
        $new_content .= "\n" . $section;      // no FAQ heading: append
        echo "note: FAQ heading not found, appending pricing section at the end\n";
    } else {
        $new_content = substr($new_content, 0, $pos) . $section . substr($new_content, $pos);
    }
}

/* ----------------------------------------------------------------- schema ---
 * AggregateOffer for the range plus one Offer per plan. UnitPriceSpecification
 * states the per-month billing explicitly: a bare price on a subscription reads
 * as a one-off charge.
 */
$offers = [];
foreach ($PLANS as [$name, $price, $desc]) {
    $offers[] = [
        '@type' => 'Offer',
        'name' => 'Website Builder ' . $name,
        'price' => $price,
        'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/InStock',
        'url' => 'https://azwebcorp.com/website-builder/',
        'priceSpecification' => [
            '@type' => 'UnitPriceSpecification',
            'price' => $price,
            'priceCurrency' => 'USD',
            'unitText' => 'MONTH',
            'billingDuration' => 1,
            'billingIncrement' => 1,
        ],
    ];
}
$aggregate = [
    '@type' => 'AggregateOffer',
    'priceCurrency' => 'USD',
    'lowPrice' => '4.99',
    'highPrice' => '19.99',
    'offerCount' => count($PLANS),
    'offers' => $offers,
];

$patched_schema = false;
$new_content = preg_replace_callback(
    '#(<script[^>]+application/ld\+json[^>]*>)(.*?)(</script>)#is',
    function ($m) use ($aggregate, &$patched_schema) {
        $data = json_decode(trim($m[2]), true);
        if (!is_array($data) || ($data['@type'] ?? '') !== 'Product') {
            return $m[0];                      // leave every other block alone
        }
        $data['offers'] = $aggregate;
        $patched_schema = true;
        return $m[1] . "\n"
             . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
             . "\n" . $m[3];
    },
    $new_content
);

echo "visible pricing section : " . ($already_visible ? "already present, left alone" : "added") . "\n";
echo "Product offers in schema: " . ($patched_schema ? "added" : "NO Product node matched") . "\n";
echo "content size            : " . strlen($content) . " -> " . strlen($new_content) . " bytes\n";

if (!$patched_schema) {
    echo "refusing to save: the Product node was not found, so the visible prices\n"
       . "would ship without matching markup.\n";
    return;
}

if (!$APPLY) {
    echo "\nDRY RUN. Re-run with 'apply' to save.\n";
    return;
}

$backup = getenv('HOME') . '/website-builder-content.bak-' . gmdate('Ymd-His') . '.html';
file_put_contents($backup, $content);
echo "backup written: {$backup}\n";

$res = wp_update_post(['ID' => $POST_ID, 'post_content' => $new_content], true);
if (is_wp_error($res)) {
    echo "SAVE FAILED: " . $res->get_error_message() . "\n";
    return;
}
clean_post_cache($POST_ID);
echo "saved post {$POST_ID}\n";
