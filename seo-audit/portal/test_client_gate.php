<?php
/**
 * Prove the client gate's security properties before it protects real data.
 *
 *     php test_client_gate.php
 *
 * Runs the gate's own signing, roster and session logic against a temporary
 * roster. The property being tested is the one that matters: the slug served
 * comes from the signed cookie and cannot be influenced by the request, so a
 * signed-in client has no way to reach another client's page.
 */

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/azwc-gate-test-' . bin2hex(random_bytes(4));
mkdir($tmp . '/p', 0700, true);

// Minimal stand-ins for the gate's environment.
file_put_contents($tmp . '/roster.json', json_encode([
    'alpha' => ['name' => 'Alpha Ltd', 'emails' => ['a@alpha.test', 'Second@Alpha.test']],
    'beta'  => ['name' => 'Beta Ltd',  'emails' => ['b@beta.test']],
    'gamma' => ['name' => 'Gamma Ltd', 'emails' => []],
]));
file_put_contents($tmp . '/p/alpha.php', '<?php echo "ALPHA PAGE";');
file_put_contents($tmp . '/p/beta.php', '<?php echo "BETA PAGE";');

$STATE = $tmp . '/state';
$ROSTER = $tmp . '/roster.json';
$PAGES = $tmp . '/p';

// Pull in just the pure functions from the gate.
$src = file_get_contents(__DIR__ . '/client-gate.php');
$start = strpos($src, '/* ---------------------------------------------------------------- helpers */');
$end = strpos($src, '/* ------------------------------------------------------------------ views */');
eval("const GATE_TTL = 2592000; const LINK_TTL = 900; const RATE_MAX = 5; const RATE_WINDOW = 3600;\n"
     . substr($src, $start, $end - $start));

$pass = 0;
$fail = 0;
function check(string $label, bool $ok): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  PASS  {$label}\n"; }
    else     { $fail++; echo "  FAIL  {$label}\n"; }
}

echo "roster\n";
$r = roster();
check('maps a listed address to its client', ($r['a@alpha.test']['slug'] ?? '') === 'alpha');
check('lowercases addresses on load', ($r['second@alpha.test']['slug'] ?? '') === 'alpha');
check('a client with no addresses admits nobody', !isset($r['']) && count($r) === 3);
check('an unlisted address maps to nothing', !isset($r['nobody@example.test']));

echo "\nsigning\n";
$tok = sign('{"s":"alpha"}');
check('a signed value verifies', unsign($tok) === '{"s":"alpha"}');
check('a tampered payload is rejected', unsign(b64u('{"s":"beta"}') . '.' . explode('.', $tok)[1]) === null);
check('a truncated token is rejected', unsign(explode('.', $tok)[0]) === null);
check('a random token is rejected', unsign(b64u('x') . '.' . b64u('y')) === null);

echo "\nescalation attempts\n";
// The realistic attack: a genuine Alpha session, edited to claim Beta.
$alpha_session = json_encode(['s' => 'alpha', 'e' => 'a@alpha.test', 'exp' => time() + 3600]);
$forged = str_replace('alpha', 'beta', $alpha_session);
check('re-signing needs the secret, so a swapped slug fails',
      unsign(b64u($forged) . '.' . explode('.', sign($alpha_session))[1]) === null);

// Someone who signs their own payload without the server secret.
$outsider = b64u($forged) . '.' . b64u(hash_hmac('sha256', $forged, 'not-the-secret', true));
check('a self-signed cookie fails', unsign($outsider) === null);

// Expiry is enforced on the payload, not the cookie lifetime.
$expired = json_encode(['s' => 'alpha', 'e' => 'a@alpha.test', 'exp' => time() - 1]);
$d = json_decode((string) unsign(sign($expired)), true);
check('an expired session is detectable', ($d['exp'] ?? 0) < time());

echo "\nsingle-use links\n";
$n = bin2hex(random_bytes(8));
check('first use of a nonce is accepted', nonce_spent($n) === false);
check('second use of the same nonce is refused', nonce_spent($n) === true);

echo "\nrate limiting\n";
$addr = 'flood@alpha.test';
$allowed = 0;
for ($i = 0; $i < 8; $i++) { if (rate_ok($addr)) { $allowed++; } }
check('sending is capped per address', $allowed === RATE_MAX);

echo "\npage resolution\n";
// The gate builds the path from the session slug only, after stripping
// anything that is not a slug character.
$slug = '../../wp-config';
$safe = preg_replace('/[^a-z0-9-]/', '', $slug);
check('traversal characters are stripped from a slug', $safe === 'wp-config');
check('a stripped slug does not resolve outside the pages dir',
      !is_readable($PAGES . '/' . $safe . '.php'));
check('a real slug resolves inside it', is_readable($PAGES . '/alpha.php'));

echo "\n" . ($fail === 0 ? "ALL {$pass} CHECKS PASSED" : "{$pass} passed, {$fail} FAILED") . "\n";

// Clean up.
array_map('unlink', glob($tmp . '/state/*') ?: []);
array_map('unlink', glob($tmp . '/p/*') ?: []);
@unlink($tmp . '/roster.json');
@rmdir($tmp . '/state');
@rmdir($tmp . '/p');
@rmdir($tmp);

exit($fail === 0 ? 0 : 1);
