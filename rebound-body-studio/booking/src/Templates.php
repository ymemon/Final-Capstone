<?php
declare(strict_types=1);

namespace Rebound;

/**
 * Email body rendering.
 *
 * Templates are just plain text files in templates/ with placeholder
 * replacement. No Twig, no Blade - simple enough to debug without
 * a templating abstraction.
 */
final class Templates
{
    public static function render(string $template, array $vars, Db $db, string $baseUrl): string
    {
        $file = __DIR__ . '/../templates/' . $template . '.txt';
        if (!is_file($file)) {
            throw new \RuntimeException("No such template: {$template}");
        }
        $body = file_get_contents($file);

        // Global replacements.
        $body = strtr($body, [
            '{STUDIO_NAME}' => $db->setting('studio_name', 'Rebound Body Studio'),
            '{STUDIO_EMAIL}' => $db->setting('studio_email', ''),
            '{STUDIO_PHONE}' => $db->setting('studio_phone', ''),
            '{STUDIO_ADDRESS}' => $db->setting('studio_address', '1138 N. Higley Rd, Suite 109, Mesa, AZ 85205'),
            '{BASE_URL}' => $baseUrl,
        ]);

        // Appointment-specific replacements.
        if (isset($vars['service_name'])) {
            $body = strtr($body, [
                '{SERVICE_NAME}' => $vars['service_name'] ?? '',
                '{DURATION_MIN}' => $vars['duration_min'] ?? '',
                '{PRICE_USD}' => !empty($vars['package_purchase_id']) ? '1 prepaid package session'
                    : (isset($vars['price_cents']) ? '$' . number_format((int) $vars['price_cents'] / 100, 2) : ''),
            ]);
        }

        if (isset($vars['starts_at'])) {
            $body = strtr($body, [
                '{WHEN}' => Clock::human($vars['starts_at']),
                '{TIME}' => Clock::humanTime($vars['starts_at']),
                '{DAY}' => Clock::humanDay($vars['starts_at']),
            ]);
        }

        if (isset($vars['client_name'])) {
            $body = strtr($body, [
                '{CLIENT_NAME}' => $vars['client_name'] ?? '',
            ]);
        }

        if (isset($vars['manage_token'])) {
            $manageUrl = $baseUrl . '/booking/public/manage.html?token=' . urlencode($vars['manage_token']);
            $body = strtr($body, [
                '{MANAGE_URL}' => $manageUrl,
                '{MANAGE_LINK}' => $manageUrl,
            ]);
        }

        if (isset($vars['cancel_reason'])) {
            $body = strtr($body, [
                '{REASON}' => $vars['cancel_reason'] ?? '(no reason given)',
            ]);
        }

        if (isset($vars['studio_note'])) {
            $body = strtr($body, [
                '{STUDIO_NOTE}' => $vars['studio_note'] ?? '',
            ]);
        }

        if (isset($vars['login_url'])) {
            $body = strtr($body, ['{LOGIN_URL}' => $vars['login_url']]);
        }

        if (isset($vars['client_email'])) {
            $unsub = $db->one(
                'SELECT unsub_token FROM clients WHERE email = ?',
                [strtolower($vars['client_email'])]
            );
            if ($unsub !== null) {
                $unsubUrl = $baseUrl . '/booking/api/unsubscribe.php?t=' . urlencode($unsub['unsub_token']);
                $body = strtr($body, [
                    '{UNSUBSCRIBE_URL}' => $unsubUrl,
                    '{UNSUBSCRIBE_LINK}' => $unsubUrl,
                ]);
            }
        }

        if (isset($vars['unsubscribe_url'])) {
            $body = str_replace('{UNSUBSCRIBE_LINK}', $vars['unsubscribe_url'], $body);
        }
        // Insert authored campaign copy last so braces in customer text stay literal.
        if (isset($vars['package_body'])) {
            return trim(str_replace('{PACKAGE_BODY}', $vars['package_body'], $body));
        }
        if (isset($vars['campaign_body'])) {
            return trim(str_replace('{CAMPAIGN_BODY}', $vars['campaign_body'], $body));
        }

        // Clean up any unreplaced placeholders (defensive).
        $body = preg_replace('/\{[A-Z_]+\}/', '', $body);

        return trim($body);
    }
}
