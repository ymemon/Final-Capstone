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

    'web-design-mesa-az' => [
        'city' => 'Mesa',
        'title' => 'Web Design in Mesa, AZ | AZWebCorp',
        'desc' => 'Website design for Mesa businesses, from a studio in Gilbert just south of '
                . 'the city line. Built for repair-intent trades, winter-visitor seasonality and '
                . 'Falcon Field B2B. Call (480) 818-5761.',
        'also_served' => 'Apache Junction, Arizona',
        // Read out of the body file so the generated FAQPage matches the
        // visible copy exactly. Do not hand-edit these; edit the body and
        // re-sync, or the page ships schema that disagrees with itself.
        'faqs' => [
            ['Are you based in Mesa?',
             'No — we are in Gilbert, at 4690 E Laurel Ave, just south of the Mesa border. For most of Mesa that is fifteen to twenty minutes, and we come to you for the first meeting and for photography. We would rather say that than list a Mesa suite number that turns out to be a mailbox.'],
            ['My customers are only in one part of Mesa. Does that matter?',
             'It matters a lot, and it is usually an advantage. A site that says which parts of the city you cover, and names the cross streets, converts better than one claiming the whole Valley — and it is easier to rank. We would rather build you a page that wins east Mesa than one that vaguely addresses everywhere.'],
            ['Do you work with manufacturers and B2B suppliers?',
             'Yes, and they need a different site from a consumer trade — capabilities, materials, tolerances, lead times and an RFQ path that accepts a drawing, rather than booking widgets. We will build that structure and then ask you for the specifics; we will not invent certifications or specifications on your behalf.'],
            ['What does a website cost?',
             'We quote per project, because it depends on page count, whether we photograph your work, and whether you need e-commerce, booking or an RFQ workflow. You get a range on the first call, before spending anything — and if an edit to your existing site would do the job more cheaply than a rebuild, we will tell you that.'],
            ['Can you fix the site I have instead of replacing it?',
             'Often, and it is usually cheaper. If the underlying site is sound, fixing load speed, mobile layout, the enquiry path and the local search signals costs a fraction of a rebuild. The free audit above is a reasonable first look at which situation you are in.'],
        ],
    ],
    'web-design-chandler-az' => [
        'city' => 'Chandler',
        'title' => 'Web Design in Chandler, AZ | AZWebCorp',
        'desc' => 'Website design for Chandler businesses, from a studio in Gilbert. Built for a technical audience and the supplier economy around the Price Road Corridor. Call (480) 818-5761.',
        'also_served' => 'Mesa, Arizona',
        // Read out of the body file, not retyped: the publisher builds
        // FAQPage from these and they must match the visible copy exactly.
        'faqs' => [
            ['Why does a technical local audience change the website?',
             'Because marketing language that works elsewhere reads as evasion here. An audience that specifies tolerances for a living notices when a site claims to be industry-leading and never says what it does. Specifics convert this audience: what you do, what it costs, how long it takes, what you will not take on.'],
            ['We want to supply the semiconductor plants. Is a website worth anything for that?',
             'Yes, but not the kind most agencies build. It gets read after a referral, not instead of one, by somebody checking whether you are credible before a meeting. That means capability statements, certifications, insurance limits, safety record and comparable past work, all findable in under two minutes and not hidden behind a contact form.'],
            ['Is Chandler more competitive than the surrounding towns?',
             'For consumer services, generally yes. Household incomes are high, which attracts more competitors and raises advertising costs, and national franchises target the area deliberately. That usually argues for competing on a narrower specialism rather than head-on for the broadest term in your category.'],
            ['Are you based in Chandler?',
             'No, we are in Gilbert, next door, and we will not rent a Chandler address to appear local. It changes nothing about your rankings, because Google weights where your business is and not where your agency is. It does mean we can be at your premises quickly.'],
        ],
    ],

    'web-design-scottsdale-az' => [
        'city' => 'Scottsdale',
        'title' => 'Web Design in Scottsdale, AZ | AZWebCorp',
        'desc' => 'Website design for Scottsdale businesses, from a studio in Gilbert. Built for a seasonal visitor economy and two audiences on one site. Call (480) 818-5761.',
        'also_served' => 'Paradise Valley, Arizona',
        // Read out of the body file, not retyped: the publisher builds
        // FAQPage from these and they must match the visible copy exactly.
        'faqs' => [
            ['Should my site be built for visitors or for locals?',
             'Usually both, on separate paths. A visitor needs to know where you are, whether you are open, and how to book, decided in about a minute on a phone. A resident needs to know you are worth returning to. One page trying to do both jobs at once generally does neither, and the fix is structural rather than a matter of wording.'],
            ['How do you handle a business whose year is one long season?',
             'By building the seasonal parts to be changed rather than rebuilt. Hours, menus, rates and closures should be editable by you in minutes, because a site that says open daily in July when you shut until October costs you trust as well as a booking. If your revenue concentrates into a few months, the site has to be right during them.'],
            ['Scottsdale design standards are high. Can you meet them?',
             'Sometimes the honest answer is no. If you need a brand identity built from scratch to sit beside luxury hospitality, a specialist studio is a better fit and we will say so. If you need a site that is genuinely fast, works properly on a phone, is easy to update and does not embarrass you next to a resort\'s, that we do.'],
            ['You are in Gilbert, not Scottsdale. Does that matter here?',
             'It matters more in Scottsdale than anywhere else we work, and not for the reason people assume. Your rankings depend on where your business is, not where your agency is. But we are about forty minutes away, so in-person meetings need planning rather than being casual. If regular face-to-face contact is important to you, that is worth weighing before you hire us.'],
        ],
    ],

    'web-design-tempe-az' => [
        'city' => 'Tempe',
        'title' => 'Web Design in Tempe, AZ | AZWebCorp',
        'desc' => 'Website design for Tempe businesses, from a studio in Gilbert. Built for a dense, landlocked city with high customer turnover. Call (480) 818-5761.',
        'also_served' => 'Phoenix, Arizona',
        // Read out of the body file, not retyped: the publisher builds
        // FAQPage from these and they must match the visible copy exactly.
        'faqs' => [
            ['Are you based in Tempe?',
             'No. We are in Gilbert, at 4690 E Laurel Ave, about twenty minutes away, and we will not rent a Tempe address to look local. It makes no difference to your rankings, because Google weights where your business is rather than where your agency is. It does mean we can come to you for photography and meetings.'],
            ['Should my Tempe business target students?',
             'Only if the margins work. Student trade is high volume, price sensitive and turns over every few years, so word of mouth compounds more slowly than it would elsewhere. If your business depends on established households or on commercial contracts, chasing the student market can absorb a great deal of attention for very little return. It is worth deciding deliberately rather than by default.'],
            ['Why does being landlocked matter to my website?',
             'Because it changes what work is available and who you are competing with. Tempe cannot expand outward, so there is far less new construction than in the towns further out and much more repair and renovation. Repair intent is urgent and decided on a phone in minutes, which rewards a fast site that names the service plainly and shows a tappable number, over one built around a homepage carousel.'],
            ['My address is a suite in a shared building. Does that hurt?',
             'It can, and it is fixable. Multi-tenant addresses confuse both customers and mapping apps, and a visitor who cannot find your door counts as a lost enquiry even though your marketing worked. A plain description of the entrance, the parking and the suite, alongside the map, recovers most of that.'],
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
/* Refuse to overwrite a page that already has real copy on it.
 *
 * Not a hypothetical: on 2026-09-08 a parallel session published Queen Creek
 * four minutes before this script's dry run, and the only thing that stopped an
 * overwrite was noticing the byte count by eye. A stub is ~45 words of
 * post_content; anything above 800 is somebody's work. `force` is deliberately
 * awkward to type. */
$existing_words = str_word_count(wp_strip_all_tags($post->post_content));
if ($existing_words >= 800 && !in_array('force', $args ?? [], true)) {
    echo "\nREFUSING: this page already has {$existing_words} words of content.\n"
       . "Someone has built it out. Diff it before replacing, then pass 'force'\n"
       . "if you still mean to.\n";
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
