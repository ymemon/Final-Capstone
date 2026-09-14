<?php
/**
 * Shared private storage for everything under /reports/ - the client gate's
 * roster and signing secret, and the ticket system's SQLite DB and Twilio
 * credentials.
 *
 * Lives INSIDE the webroot deliberately. $HOME/.portal-*-state looked like
 * outside-webroot-but-still-readable storage, and client-gate.php was built
 * on that assumption, but live testing on 2026-09-13 proved it silently
 * fails: web PHP runs in a different chroot than SSH on this host, so
 * getenv('HOME') can resolve to a path that is real and correct-looking as
 * a STRING while pointing at nothing web PHP can actually see - the exact
 * failure mode the GA4 key integration hit on 2026-09-07 for a different
 * file. That earlier discovery already proved the fix: the one place a
 * secret reliably persists across both a write and a later read from a live
 * web request on this host is inside the webroot, in a file PHP EXECUTES
 * rather than serves.
 *
 * THE SAFETY PROPERTY, STATED PLAINLY
 * A file containing `<?php return <value>;` and nothing else, requested
 * directly over HTTP, executes: the `return` ends the script with zero
 * bytes written to the response. That is the entire protection - not the
 * directory name, not the file extension, not .htaccess (AuthType/
 * AuthUserFile directives are provably ignored on this host; a bare `Require
 * all denied` MAY help and costs nothing, but must never be the only layer
 * trusted). This property only holds for genuine `<?php ... ?>` content.
 *
 * It does NOT hold for the SQLite database file, which is opened directly
 * by PDO/sqlite3 and cannot be wrapped in PHP syntax - a raw binary file has
 * no `<?php` tag, so a direct request for it would have PHP pass the whole
 * file through as literal output, which is worse than not "protecting" it
 * at all. That file is instead given an unguessable, randomly generated
 * name (see db.php) so there is no path to guess in the first place, on top
 * of the same directory-level `.htaccess` deny.
 */

declare(strict_types=1);

/** /reports/ itself, regardless of whether this copy of the file is running
 *  from html/reports/ (client-facing) or html/reports/_owner/ (agent-facing)
 *  - both must resolve to the SAME directory so they share one database and
 *  one roster rather than two silently diverging copies. */
function reports_root(): string {
    return basename(__DIR__) === '_owner' ? dirname(__DIR__) : __DIR__;
}

function state_dir(): string {
    $dir = reports_root() . '/_state';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        // Best-effort second layer - see this file's doc comment for why it
        // is never the only thing relied on.
        @file_put_contents($ht, "Require all denied\nOptions -Indexes\n");
    }
    return $dir;
}

function state_path(string $name): string {
    return state_dir() . '/' . $name . '.php';
}

/** Reads a `<?php return ...;` state file. Returns $default if the file is
 *  missing, unreadable, or (via PHP's own `include` return-value contract)
 *  had no return statement at all. */
function state_read(string $name, $default) {
    $f = state_path($name);
    if (!is_readable($f)) {
        return $default;
    }
    $v = include $f;
    return $v === 1 ? $default : $v;
}

/** Atomically rewrites a state file as `<?php return <php literal>;`. The
 *  write goes to a sibling temp file first and is renamed into place so a
 *  concurrent reader never sees a half-written file. */
function state_write(string $name, $value): void {
    $f = state_path($name);
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, "<?php\nreturn " . var_export($value, true) . ";\n");
    rename($tmp, $f);
}

/** Path for an opaque/binary state file such as the SQLite DB - never
 *  `include`d, only opened directly. $name should already be an unguessable
 *  token; this does not add one. */
function state_raw_path(string $name): string {
    state_dir(); // ensure the directory (and its .htaccess) exist first
    return state_dir() . '/' . $name;
}
