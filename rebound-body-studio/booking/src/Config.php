<?php
declare(strict_types=1);

namespace Rebound;

/** Runtime paths that must remain outside the public web root in production. */
final class Config
{
    public static function databasePath(): string
    {
        $override = trim((string) getenv('REBOUND_DB_PATH'));
        if ($override !== '') {
            return $override;
        }

        // GoDaddy exposes the web root through /html. Its server rejects
        // dotfiles before static content reaches PHP, while application code
        // can still read them. Keep SQLite under an HTTP-blocked dotfile name.
        if (DIRECTORY_SEPARATOR === '/') {
            return dirname(__DIR__) . '/.booking.db';
        }

        // Local development keeps its disposable database beside the app.
        return dirname(__DIR__) . '/booking.db';
    }
}
