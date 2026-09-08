<?php
/**
 * Publish a built-out city page: body from a file, schema generated, metadata set.
 *
 *   wp eval-file azw-city-page-publish.php <slug> <body-file>          # dry run
 *   wp eval-file azw-city-page-publish.php <slug> <body-file> apply
 *
 * The body lives in its own .html file rather than a heredoc in here. That is
 * partly separation of content from logic, and partly because a 16KB script
 * with the copy embedded produced no output at all under wp eval-file - it
 * linted clean and exited 0 in silence, which is a miserable thing to debug
 * against a live site. A small script reading a file is verifiable.
 *
 * Emits BreadcrumbList, ProfessionalService and FAQPage in the same shape as
 * /web-design-gilbert-az/, sets rank_math_title / description / canonical, and
 * flips rank_math_robots to index,follow. Backs up the previous content and
 * metadata first, and refuses to publish copy shorter than the Gilbert
 * benchmark.
 */

$slug = $args[0] ?? '';
$body_file = $args[1] ?? '';
$APPLY = in_array('apply', $args ?? [], true);

$CITIES = [
    'web-design-queen-creek-az' => [
        'city' => 'Queen Creek',
        'title' => 'Web Design in Queen Creek, AZ | AZWebCorp',
        'desc' => 'Website design for Queen Creek businesses, from a studio in Gilbert. '
                . 'Sites for trades, home services and agritourism, built for a service area '
                . 'that crosses the Pinal county line. Call (480) 818-5761.',
        'also_served' => 'San Tan Valley, Arizona',
        'faqs' => [
            ['Are you actually based in Queen Creek?',
             'No. Our office is at 4690 E Laurel Ave in Gilbert, roughly twenty miles and twenty-five to thirty minutes from Queen Creek. We come to you for the first meeting and for photography.'],
            ['Do you cover San Tan Valley as well?',
             'Yes. If your customers are in San Tan Valley it needs to be stated explicitly on your site, because many Queen Creek businesses lose those enquiries simply because nothing on the page tells that customer they are in the service area.'],
            ['What does a website cost in Queen Creek?',
             'We quote per project, because cost depends on the number of pages, whether we photograph your work, and whether you need booking or e-commerce. We give you a range on the first call before you have spent anything.'],
            ['How long does a website take?',
             'A straightforward brochure and gallery site is typically a few weeks from the point where photographs and copy decisions are settled. Waiting on content, not the build, is what slows projects down.'],
            ['Can you fix the website I already have?',
             'Often yes, and it is usually cheaper than a rebuild. If the underlying site is sound, fixing speed, mobile layout, the enquiry form and local search signals costs a fraction of starting again.'],
        ],
    ],
];

if (!isset($CITIES[$slug])) {
    echo "unknown slug '{$slug}'. known: " . implode(', ', array_keys($CITIES)) . "\n";
    return;
}
$cfg = $CITIES[$slug];

if (!is_readable($body_file)) {
    echo "cannot read body file: {$body_file}\n";
    return;
}
$body = file_get_contents($body_file);
$words = str_word_count(wp_strip_all_tags($body));

$posts = get_posts(['name' => $slug, 'post_type' => 'page', 'numberposts' => 1, 'post_status' => 'any']);
if (!$posts) { echo "no page with slug '{$slug}'\n"; return; }
$post = $posts[0];
$id = $post->ID;
$url = get_permalink($id);

$enc = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

$area = [['@type' => 'City', 'name' => $cfg['city'],
          'containedInPlace' => ['@type' => 'State', 'name' => 'Arizona']]];
if (!empty($cfg['also_served'])) {
    $area[] = ['@type' => 'Place', 'name' => $cfg['also_served']];
}

$breadcrumb = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url('/')],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Arizona Web Development',
     'item' => 'https://azwebcorp.com/web-development/'],
    ['@type' => 'ListItem', 'position' => 3, 'name' => 'Web Design ' . $cfg['city'] . ', AZ', 'item' => $url],
]];

$service = [
    '@context' => 'https://schema.org',
    '@type' => 'ProfessionalService',
    'name' => 'AZWebCorp',
    'description' => 'Website design for businesses in ' . $cfg['city']
                   . ', Arizona, delivered from a studio in Gilbert.',
    'url' => $url,
    'telephone' => '+1-480-818-5761',
    'email' => 'info@azwebcorp.com',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => '4690 E Laurel Ave',
        'addressLocality' => 'Gilbert',
        'addressRegion' => 'AZ',
        'postalCode' => '85234',
        'addressCountry' => 'US',
    ],
    'areaServed' => $area,
];

$entities = [];
foreach ($cfg['faqs'] as $qa) {
    $entities[] = ['@type' => 'Question', 'name' => $qa[0],
                   'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]];
}
$faq = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities];

$schema = '';
foreach ([$breadcrumb, $service, $faq] as $node) {
    $schema .= "\n\n<script type=\"application/ld+json\">\n" . json_encode($node, $enc) . "\n</script>";
}
$new_content = rtrim($body) . $schema . "\n";

$robots_now = get_post_meta($id, 'rank_math_robots', true);
echo "page        : {$id} ({$slug})\n";
echo "body words  : {$words}\n";
echo "content     : " . strlen($post->post_content) . " -> " . strlen($new_content) . " bytes\n";
echo "schema      : BreadcrumbList, ProfessionalService, FAQPage (" . count($entities) . " questions)\n";
echo "title       : {$cfg['title']}\n";
echo "robots      : " . json_encode($robots_now) . " -> [index, follow]\n";

if ($words < 1200) {
    echo "\nREFUSING: {$words} words is short of the Gilbert benchmark (~1,400).\n";
    return;
}
if (strpos($post->post_content, 'county line runs straight through') !== false) {
    echo "\nalready built out - nothing to do\n";
    return;
}
if (!$APPLY) { echo "\nDRY RUN. Add 'apply' to save.\n"; return; }

$backup = getenv('HOME') . "/{$slug}-before-" . gmdate('Ymd-His') . '.json';
file_put_contents($backup, json_encode([
    'post_content' => $post->post_content,
    'rank_math_title' => get_post_meta($id, 'rank_math_title', true),
    'rank_math_description' => get_post_meta($id, 'rank_math_description', true),
    'rank_math_robots' => $robots_now,
    'rank_math_canonical_url' => get_post_meta($id, 'rank_math_canonical_url', true),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "backup      : {$backup}\n";

$res = wp_update_post(['ID' => $id, 'post_content' => $new_content], true);
if (is_wp_error($res)) { echo "SAVE FAILED: " . $res->get_error_message() . "\n"; return; }

update_post_meta($id, 'rank_math_title', $cfg['title']);
update_post_meta($id, 'rank_math_description', $cfg['desc']);
update_post_meta($id, 'rank_math_canonical_url', $url);
update_post_meta($id, 'rank_math_robots', ['index', 'follow']);
clean_post_cache($id);

echo "saved. robots now index,follow\n";
