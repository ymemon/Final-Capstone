<?php
/**
 * Answer generation.
 *
 * Two modes, chosen by whether an API key is configured:
 *
 *   LIVE  — Claude answers, given the retrieved page context. Prompt caching
 *           is applied to the stable system prompt so the knowledge base is
 *           billed at ~10% after the first message of a conversation. That
 *           is what keeps a conversation at single-digit cents.
 *
 *   DEMO  — no key, no spend, no external call. Answers are assembled from
 *           the best-matching published page. Useful for showing the whole
 *           flow before anyone pays for anything.
 */

defined('ABSPATH') || exit;

class EIT_Chat_Brain {

    const API_URL     = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2023-06-01';
    const MAX_TOKENS  = 1024;

    public static function is_live() {
        return trim((string) EIT_Chat_Store::opt('eit_chat_api_key')) !== '';
    }

    public static function assistant_name() {
        $n = trim((string) EIT_Chat_Store::opt('eit_chat_assistant_name'));
        return $n !== '' ? $n : 'Niall';
    }

    /**
     * The opening line. Carries the AI disclosure — the visitor is told what
     * they are talking to in the first message, before they say anything.
     */
    public static function greeting() {
        $name = self::assistant_name();
        return sprintf(
            "Hi, I'm %s — Everything IT's AI assistant. I can answer questions about our IT support, hardware and cloud services right away, and I'll pass you to one of the team whenever you'd like. What can I help you with?",
            $name
        );
    }

    /**
     * @return array{reply:string, handoff:bool, sources:array}
     */
    public static function answer($session, array $history, $question) {
        $sources = EIT_Chat_KB::search($question, 4);

        if (!self::is_live()) {
            return self::demo_answer($question, $sources);
        }

        $result = self::live_answer($history, $question, $sources);
        if ($result === null) {
            // API failed — degrade to the offline path rather than dead-ending
            // the visitor. They still get a useful answer and a way through.
            $fallback = self::demo_answer($question, $sources);
            $fallback['handoff'] = true;
            return $fallback;
        }
        return $result;
    }

    /* ── live ────────────────────────────────────────────────────────── */

    private static function live_answer(array $history, $question, array $sources) {
        $key = trim((string) EIT_Chat_Store::opt('eit_chat_api_key'));

        $messages = [];
        foreach ($history as $h) {
            if ($h['role'] === 'visitor') {
                $messages[] = ['role' => 'user', 'content' => $h['body']];
            } elseif ($h['role'] === 'assistant' || $h['role'] === 'staff') {
                $messages[] = ['role' => 'assistant', 'content' => $h['body']];
            }
        }

        $context = self::render_sources($sources);
        $messages[] = [
            'role'    => 'user',
            'content' => $context === ''
                ? $question
                : "Relevant pages from the Everything IT website:\n\n{$context}\n\n---\nVisitor's message: {$question}",
        ];

        $body = [
            'model'      => EIT_Chat_Store::opt('eit_chat_model'),
            'max_tokens' => self::MAX_TOKENS,
            // Stable prefix, cached: the whole system prompt is billed at
            // ~10% on every message after the first in a conversation.
            'system'     => [[
                'type'          => 'text',
                'text'          => self::system_prompt(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => $messages,
        ];

        $res = wp_remote_post(self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'content-type'      => 'application/json',
                'x-api-key'         => $key,
                'anthropic-version' => self::API_VERSION,
            ],
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($res)) {
            self::log_failure($res->get_error_message());
            return null;
        }
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);

        if ($code !== 200 || !is_array($json)) {
            $msg = is_array($json) && isset($json['error']['message'])
                ? $json['error']['message'] : 'HTTP ' . $code;
            self::log_failure($msg);
            return null;
        }

        $text = '';
        foreach (($json['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return [
            'reply'   => $text,
            'handoff' => self::wants_handoff($text),
            'sources' => $sources,
        ];
    }

    private static function log_failure($message) {
        update_option('eit_chat_last_error', [
            'at'      => current_time('mysql'),
            'message' => (string) $message,
        ], false);
    }

    private static function render_sources(array $sources) {
        $out = [];
        foreach ($sources as $s) {
            $out[] = "## {$s['title']}\nURL: {$s['url']}\n{$s['text']}";
        }
        return implode("\n\n", $out);
    }

    /**
     * The behavioural contract. Two rules matter more than the rest: never
     * invent a commercial fact, and hand to a human rather than guess.
     */
    private static function system_prompt() {
        $name = self::assistant_name();

        return <<<TXT
You are {$name}, the assistant on the Everything IT website (everythingit.ie).

ABOUT EVERYTHING IT
A Dublin-based managed IT services and cybersecurity company serving Irish
businesses. Services include managed IT support, cybersecurity, cloud and
Microsoft 365, hardware procurement and deployment, IT procurement,
professional services, disaster recovery, VoIP, and ISO 27001 compliance.
Offices at Avonbeg Industrial Estate, Longmile Road, Dublin 12. They work
with businesses of 15 employees and upwards.

WHO YOU ARE
You are an AI assistant, and the opening message already told the visitor so.
If anyone asks whether you are a person, say plainly that you are an AI
assistant and offer to put them through to the team. Never claim to be human,
never invent a personal history, and never say you have attended a call or
visited a site.

WHAT YOU DO
Answer questions about Everything IT's services using the page content
supplied with each message. Be brief and concrete — two or three short
paragraphs at most, usually less. Write plainly, the way a knowledgeable
colleague would in a chat window. No bullet-point dumps unless genuinely
listing options. Link to the relevant page when it helps.

WHAT YOU MUST NOT DO
Never invent commercial facts. You do not know and must not guess:
  - prices, rates, day rates or quotes of any kind
  - contract lengths, SLA response times or specific guarantees
  - hardware vendors or brands they resell, lease vs purchase terms, warranty terms
  - staff names, availability, or anything about a specific person
If asked about any of these, say it is something the team will confirm, and
offer a callback. That is a better answer than a plausible wrong one.

WHEN TO HAND OVER
Offer to take a name, email or phone number and have the team come back to
them when: they ask for a price or a quote, they want to speak to someone,
they describe an urgent or live IT problem, they ask something you cannot
answer from the pages provided, or they simply seem to want a person. When
you make that offer, end your message with the exact token [[HANDOFF]] on its
own line. The visitor never sees that token; it opens the contact form.

TONE
Warm, direct, unfussy. Irish business audience. No exclamation marks, no
salesy language, no emoji. If you do not know, say so.
TXT;
    }

    private static function wants_handoff($text) {
        return strpos($text, '[[HANDOFF]]') !== false;
    }

    public static function strip_handoff($text) {
        return trim(str_replace('[[HANDOFF]]', '', $text));
    }

    /* ── demo (no key, no spend) ─────────────────────────────────────── */

    /**
     * Questions whose answer is a commercial fact. Term-overlap retrieval will
     * happily return *some* page for "what does it cost" — and that page is
     * always wrong. Defer instead: the same rule the live system prompt
     * enforces, applied crudely so demo mode cannot invent a commercial answer.
     */
    const COMMERCIAL = ['price','pricing','cost','costs','quote','quotation','how much','rate','rates','fee','fees','charge','budget','cheap','expensive','discount','sla','response time','warranty','contract length','per month','per user','monthly cost'];

    /** Below this, a keyword match is coincidence rather than relevance. */
    const MIN_SCORE = 6;

    private static function is_commercial($question) {
        $q = strtolower((string) $question);
        foreach (self::COMMERCIAL as $needle) {
            if (strpos($q, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function demo_answer($question, array $sources) {
        if (self::is_commercial($question)) {
            return [
                'reply'   => "Pricing depends on how many users and devices you have and what level of cover you need, so it isn't something I can quote accurately — I'd rather not give you a number that turns out to be wrong.\n\nLeave me your name and a phone number or email and someone will come back to you with a real figure for your setup.",
                'handoff' => true,
                'sources' => [],
            ];
        }

        if (!$sources || $sources[0]['score'] < self::MIN_SCORE) {
            return [
                'reply'   => "That's not something I can answer from what's published on the site, and I'd rather tell you that than guess.\n\nIf you leave me your name and the best way to reach you, I'll have one of the team come back to you properly.",
                'handoff' => true,
                'sources' => [],
            ];
        }

        $top  = $sources[0];
        $snip = self::first_sentences($top['text'], 2);

        $reply = sprintf(
            "Here's what we publish on %s:\n\n%s\n\nYou can read the full detail at %s.",
            $top['title'], $snip, $top['url']
        );

        if (count($sources) > 1) {
            $also = [];
            foreach (array_slice($sources, 1, 2) as $s) {
                $also[] = $s['title'];
            }
            $reply .= "\n\nRelated: " . implode(', ', $also) . '.';
        }

        $reply .= "\n\nIf you'd like specifics for your own setup, leave me your name and number and the team will come back to you.";

        return ['reply' => $reply, 'handoff' => true, 'sources' => $sources];
    }

    private static function first_sentences($text, $n = 2) {
        $parts = preg_split('/(?<=[.!?])\s+/u', $text, $n + 1);
        $out   = implode(' ', array_slice($parts, 0, $n));
        return trim($out) !== '' ? trim($out) : trim($text);
    }
}
