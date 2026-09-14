<?php
/**
 * The Tier 2 taxonomy: every "complicated" request category and its fixed,
 * uniform discovery questions.
 *
 * Per yasir (2026-09-12): the point of these questions is that a ticket
 * should arrive with everything needed to do the work already answered - the
 * human reading the ticket should never have to write back to the client to
 * ask a clarifying question first. That only works if the question set per
 * category is fixed and specific, not a single free-text box, so this file
 * is the one place that taxonomy is defined; ticket_api.php and the client
 * UI both read it rather than duplicating it.
 *
 * Every category gets the same two closing questions appended in code (see
 * closing_questions() below) so "deadline" and "publish preference" are
 * always in the same place on every ticket regardless of category.
 *
 * Question 'type' values the client UI understands: text, textarea, url,
 * date, select (with 'options'). Nothing here executes anything - it is
 * config read by ticket_api.php to build the intake form and to validate
 * that every required question got an answer before a ticket is created.
 */

declare(strict_types=1);

function ticket_categories(): array {
    return [
        'content_update' => [
            'label' => 'Content Update',
            'description' => 'Change text or images on a page that already exists.',
            'questions' => [
                ['key' => 'page', 'label' => 'Which page? (URL, or a plain description like "homepage")', 'type' => 'text', 'required' => true],
                ['key' => 'change', 'label' => 'Exact new content - paste the text, or describe the before/after change', 'type' => 'textarea', 'required' => true],
                ['key' => 'mode', 'label' => 'Is this replacing existing content, or adding to it?', 'type' => 'select', 'options' => ['Replacing', 'Adding'], 'required' => true],
                ['key' => 'reference', 'label' => 'Any reference example (screenshot, competitor page, attached file)?', 'type' => 'textarea', 'required' => false],
            ],
        ],
        'new_page' => [
            'label' => 'New Page / Feature Request',
            'description' => 'Something that does not exist on the site yet.',
            'questions' => [
                ['key' => 'purpose', 'label' => "Purpose of this page/feature - who is it for, what should a visitor do on it?", 'type' => 'textarea', 'required' => true],
                ['key' => 'location', 'label' => 'Where should it live? (new URL, menu location)', 'type' => 'text', 'required' => true],
                ['key' => 'content_ready', 'label' => 'Is content ready (text/images), or do you need help creating it?', 'type' => 'select', 'options' => ['Content is ready', 'Need help creating content'], 'required' => true],
                ['key' => 'examples', 'label' => 'Any example sites/pages you like the look or feel of?', 'type' => 'textarea', 'required' => false],
            ],
        ],
        'metric_investigation' => [
            'label' => 'Metric Investigation',
            'description' => '"Why did this number change" questions.',
            'questions' => [
                ['key' => 'metric', 'label' => 'Which metric are you concerned about? (traffic, rankings, clicks, leads, etc.)', 'type' => 'text', 'required' => true],
                ['key' => 'period', 'label' => 'What time period are you comparing?', 'type' => 'text', 'required' => true],
                ['key' => 'source', 'label' => 'Where did you see this? (the portal dashboard, Google Analytics, Search Console, elsewhere)', 'type' => 'text', 'required' => true],
                ['key' => 'scope', 'label' => 'A specific page/keyword/campaign, or overall?', 'type' => 'text', 'required' => false],
                ['key' => 'concurrent_change', 'label' => 'Did anything change on your end around the same time? (a promotion, a paused ad, a site change)', 'type' => 'textarea', 'required' => false],
            ],
        ],
        'technical_issue' => [
            'label' => "Something's Broken",
            'description' => 'A technical problem on the live site.',
            'questions' => [
                ['key' => 'what_broke', 'label' => 'What exactly is broken?', 'type' => 'textarea', 'required' => true],
                ['key' => 'page', 'label' => 'Which page/URL?', 'type' => 'text', 'required' => true],
                ['key' => 'device', 'label' => 'Device/browser you saw it on', 'type' => 'text', 'required' => false],
                ['key' => 'frequency', 'label' => 'Happening every time, or intermittently?', 'type' => 'select', 'options' => ['Every time', 'Intermittently', 'Happened once'], 'required' => true],
                ['key' => 'screenshot_note', 'label' => 'Screenshot attached? (describe it, or note that one is attached separately)', 'type' => 'text', 'required' => false],
            ],
        ],
        'strategy_consultation' => [
            'label' => 'Strategy / Consultation',
            'description' => 'Bigger-picture questions before committing to work.',
            'questions' => [
                ['key' => 'goal', 'label' => "What's the business goal? (more leads, more sales of X, brand awareness)", 'type' => 'textarea', 'required' => true],
                ['key' => 'budget', 'label' => 'Budget range in mind?', 'type' => 'text', 'required' => false],
                ['key' => 'prior_attempts', 'label' => 'Have you tried something like this before? What happened?', 'type' => 'textarea', 'required' => false],
                ['key' => 'competitors', 'label' => 'Any competitors/examples you want us to look at?', 'type' => 'text', 'required' => false],
            ],
        ],
        'design_branding' => [
            'label' => 'Design / Branding Change',
            'description' => 'Visual changes: colors, logo, layout, fonts.',
            'questions' => [
                ['key' => 'what_changes', 'label' => "What's changing? (colors, logo, layout, fonts)", 'type' => 'textarea', 'required' => true],
                ['key' => 'scope', 'label' => 'Site-wide or specific page(s)?', 'type' => 'text', 'required' => true],
                ['key' => 'assets', 'label' => 'Brand assets to share? (logo files, color codes, guidelines - describe/attach)', 'type' => 'textarea', 'required' => false],
                ['key' => 'references', 'label' => 'Any reference designs you like?', 'type' => 'textarea', 'required' => false],
            ],
        ],
    ];
}

/** Appended to every category so deadline/publish-preference are always in
 *  the same two ticket columns (tickets.deadline, tickets.publish_preference)
 *  no matter which category was chosen. */
function closing_questions(): array {
    return [
        ['key' => 'deadline', 'label' => 'Deadline / target date (or "no rush")', 'type' => 'text', 'required' => false],
        ['key' => 'publish_preference', 'label' => 'Should this publish automatically once done, or do you want to review it first?', 'type' => 'select', 'options' => ['Review first', 'Publish automatically'], 'required' => true],
    ];
}

function ticket_category(string $key): ?array {
    return ticket_categories()[$key] ?? null;
}

/** All questions for a category in submission order, closing questions last -
 *  the single source both the intake form and the server-side validator use. */
function ticket_category_questions(string $key): array {
    $cat = ticket_category($key);
    if ($cat === null) {
        return [];
    }
    return array_merge($cat['questions'], closing_questions());
}
