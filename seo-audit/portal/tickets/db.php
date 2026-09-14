<?php
/**
 * Opens (and, on first run, creates) the ticket system's SQLite database.
 *
 * State lives inside the webroot via state_lib.php - see that file's doc
 * comment for why $HOME-based storage (this file's approach until
 * 2026-09-13) never actually worked from a live web request on this host,
 * despite looking correct.
 *
 * The database file itself cannot use state_lib.php's normal `<?php return
 * ...;` pattern (that only protects genuine PHP source, and a raw SQLite
 * file has no `<?php` tag - PHP would pass its bytes straight through as
 * literal output to a direct request). Instead its filename is an
 * unguessable random token, generated once and remembered via the normal
 * safe pattern, so there is no path to guess in the first place.
 */

declare(strict_types=1);

require_once __DIR__ . '/state_lib.php';

function tickets_db_path(): string {
    $token = state_read('db_token', null);
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(16));
        state_write('db_token', $token);
    }
    return state_raw_path('d-' . $token . '.dat');
}

/** One PDO connection per request, memoized. Foreign keys are off by default
 *  in SQLite and must be turned on per-connection, not just in the schema. */
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $path = tickets_db_path();
    $isNew = !file_exists($path);

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL'); // readers (agent view) don't block a client's write

    if ($isNew) {
        @chmod($path, 0600);
    }
    // Every statement in schema.sql is CREATE TABLE/INDEX IF NOT EXISTS or
    // INSERT OR IGNORE, so re-running the whole file on every request is a
    // few no-op checks on an existing install and the only way a table added
    // to schema.sql later (like todos, added 2026-09-12) shows up without a
    // separate migration step to remember to run after a deploy.
    apply_schema($pdo);

    return $pdo;
}

function apply_schema(PDO $pdo): void {
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql is missing next to db.php');
    }
    $pdo->exec($sql);
}

/** Small helper used throughout: current UTC timestamp in the same format
 *  schema.sql's column defaults use, for rows this code updates in place
 *  (e.g. tickets.updated_at) rather than lets SQLite default. */
function now_iso(): string {
    return gmdate('Y-m-d\TH:i:s.v\Z');
}
