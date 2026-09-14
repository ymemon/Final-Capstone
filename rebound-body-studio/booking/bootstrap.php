<?php
declare(strict_types=1);

namespace Rebound;

// Database initialization is an operator-only CLI action, never a web route.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Bootstrap the booking system:
 *   1. Create/migrate the database
 *   2. Seed initial data (services, staff, availability, settings)
 *
 * Run once on first deploy:
 *   php bootstrap.php
 */

require_once __DIR__ . '/src/Clock.php';
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/Config.php';

$dbPath = Config::databasePath();
$dbDir = dirname($dbPath);
if (!is_dir($dbDir) && !mkdir($dbDir, 0700, true) && !is_dir($dbDir)) {
    throw new \RuntimeException("Could not create private data directory: {$dbDir}");
}

// Create or connect to the database.
$db = Db::sqlite($dbPath);

echo "Initializing booking database...\n";

// Run migrations.
$schema = file_get_contents(__DIR__ . '/migrations/001_schema.sql');
$db->pdo()->exec($schema);
$db->pdo()->exec(file_get_contents(__DIR__ . '/migrations/002_campaigns.sql'));
echo "✓ Schema created\n";

// Seed settings.
$settings = [
    // Points at the live preview, not the parked reboundbodystudio.com domain.
    // Update this the day the studio's own domain actually has hosting behind it.
    'site_url'              => 'https://azwebcorp.com/preview/rebound',
    'studio_name'           => 'Rebound Body Studio',
    'studio_email'          => 'massage@reboundbodystudio.com',
    'studio_phone'          => '(480) 944-0494',
    // Until reboundbodystudio.com has authenticated outbound-mail DNS, send
    // through AZWebCorp's SPF/DMARC-aligned address. Replies still go directly
    // to the studio_email address.
    'mail_from'             => 'info@azwebcorp.com',
    'mail_from_name'        => 'Rebound Body Studio',
    'buffer_min'            => '15',
    'slot_granularity_min'  => '30',
    'min_notice_hours'      => '12',
    'max_advance_days'      => '90',
    'reminder_lead_hours'   => '24',
];

foreach ($settings as $key => $value) {
    $db->setSetting($key, $value);
}
echo "✓ Settings configured\n";

// Seed services (as per the website's current list).
$services = [
    ['Myofascial Unwinding', 'myofascial-unwinding', [[30, 70], [60, 95], [90, 130], [120, 175]]],
    ['Swedish Massage', 'swedish-massage', [[30, 60], [60, 85], [90, 120], [120, 160]]],
    ['Deep Tissue', 'deep-tissue', [[30, 65], [60, 90], [90, 125], [120, 170]]],
    ['Sports Massage', 'sports-massage', [[30, 70], [60, 95], [90, 130], [120, 175]]],
    ['Relaxation Massage', 'relaxation-massage', [[30, 55], [60, 80], [90, 115], [120, 150]]],
    ['Cupping', 'cupping', [[30, 55], [60, 75]]],
];

$serviceIds = [];
foreach ($services as $idx => [$name, $slug, $durations]) {
    // Insert one entry per duration (simple approach: separate rows for each length).
    foreach ($durations as [$durationMin, $priceCents]) {
        $id = $db->insert(
            'INSERT INTO services (slug, name, duration_min, price_cents, sort_order, active)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$slug . '_' . $durationMin, $name . ' (' . $durationMin . 'min)', $durationMin, $priceCents * 100, $idx, 1]
        );
        $serviceIds[$slug . '_' . $durationMin] = $id;
    }
}
echo "✓ " . count($serviceIds) . " service variants seeded\n";

// Seed staff: Randi (bookable) and Wendy (non-bookable).
$randiId = $db->insert(
    'INSERT INTO staff (name, credentials, bio, bookable, active, sort_order)
     VALUES (?, ?, ?, ?, ?, ?)',
    [
        'Randi',
        'Licensed Massage Therapist, Arizona licence MT-50515',
        'Specializing in therapeutic massage with 10+ years of experience.',
        1,
        1,
        1,
    ]
);

$wendy = $db->insert(
    'INSERT INTO staff (name, credentials, bio, bookable, active, sort_order)
     VALUES (?, ?, ?, ?, ?, ?)',
    [
        'Wendy',
        'Certified Personal Trainer',
        'Wendy is available for private bookings. Contact the studio for details.',
        0,
        1,
        2,
    ]
);

echo "✓ Staff seeded (Randi, Wendy)\n";

// Seed availability rules for Randi: Monday-Friday, 9am-1pm and 2pm-6pm.
$daysWork = [
    [1, '09:00', '13:00'], // Monday
    [1, '14:00', '18:00'],
    [2, '09:00', '13:00'], // Tuesday
    [2, '14:00', '18:00'],
    [3, '09:00', '13:00'], // Wednesday
    [3, '14:00', '18:00'],
    [4, '09:00', '13:00'], // Thursday
    [4, '14:00', '18:00'],
    [5, '09:00', '13:00'], // Friday
    [5, '14:00', '18:00'],
];

foreach ($daysWork as [$weekday, $start, $end]) {
    $db->run(
        'INSERT INTO availability_rules (staff_id, weekday, start_local, end_local)
         VALUES (?, ?, ?, ?)',
        [$randiId, $weekday, $start, $end]
    );
}
echo "✓ Availability rules set (Mon-Fri, 9am-1pm & 2pm-6pm)\n";

// Seed a sample package.
$db->insert(
    'INSERT INTO packages (slug, name, service_id, sessions, price_cents, expires_days, active, sort_order)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['6-massage-pkg', '6 Massage Sessions', null, 6, 45000, 180, 1, 1]
);
echo "✓ Sample package seeded\n";
$db->pdo()->exec(file_get_contents(__DIR__ . '/migrations/003_packages.sql'));
$db->pdo()->exec(file_get_contents(__DIR__ . '/migrations/004_current_catalog.sql'));
$db->pdo()->exec(file_get_contents(__DIR__ . '/migrations/005_square.sql'));

echo "\n✅ Booking system initialized successfully!\n";
echo "Database: {$dbPath}\n";
echo "Next: deploy the HTTP endpoints and test via /api/slots?serviceId=1\n";
