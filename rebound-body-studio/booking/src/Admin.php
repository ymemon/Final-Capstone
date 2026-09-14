<?php
declare(strict_types=1);
namespace Rebound;

final class Admin
{
    public static function loginEmail(Db $db): string
    {
        $override = trim($db->setting('admin_login_email', ''));
        return strtolower($override !== '' ? $override : trim($db->setting('studio_email', '')));
    }

    public static function authorized(Db $db, string $token): bool
    {
        return $token !== '' && $db->one(
            'SELECT lt.id FROM login_tokens lt JOIN clients c ON c.id = lt.client_id
             WHERE lt.token_hash = ? AND lt.expires_at > ? AND lt.used_at IS NULL
               AND lower(c.email) = lower(?)',
            [hash('sha256', $token), Clock::nowSql(), self::loginEmail($db)]
        ) !== null;
    }

    public static function requireToken(Db $db, string $token): void
    {
        if (!self::authorized($db, $token)) {
            http_response_code(401);
            echo json_encode(['error' => 'Your session has expired. Please request a new login link.']);
            exit;
        }
    }
}
