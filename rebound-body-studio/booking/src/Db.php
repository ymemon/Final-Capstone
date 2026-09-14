<?php
declare(strict_types=1);

namespace Rebound;

/**
 * Thin PDO wrapper. Deliberately thin - this is a booking system for one
 * therapist, not a platform, and an ORM here would be more code to maintain
 * than the thing it abstracts.
 *
 * Works against SQLite locally and MySQL in production. The only dialect
 * difference the application can see is exposed through supportsForUpdate(),
 * because row locking is the one place the two genuinely differ in a way that
 * changes correctness rather than syntax.
 */
final class Db
{
    private \PDO $pdo;
    private string $driver;

    public function __construct(string $dsn, string $user = '', string $pass = '')
    {
        $this->pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $this->driver = (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($this->driver === 'sqlite') {
            // Off by default in SQLite, and this schema leans on them.
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            // Without this, two concurrent writers produce "database is
            // locked" instead of waiting - which in a booking system means a
            // dropped request rather than a slow one.
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public static function sqlite(string $path): self
    {
        return new self('sqlite:' . $path);
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * SQLite has no SELECT ... FOR UPDATE; it serialises writers instead, so
     * a transaction is already sufficient there. MySQL needs the explicit
     * lock to stop two overlapping bookings both passing their conflict check.
     */
    public function supportsForUpdate(): bool
    {
        return $this->driver !== 'sqlite';
    }

    public function run(string $sql, array $args = []): \PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        return $st;
    }

    public function all(string $sql, array $args = []): array
    {
        return $this->run($sql, $args)->fetchAll();
    }

    public function one(string $sql, array $args = []): ?array
    {
        $row = $this->run($sql, $args)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $args = [])
    {
        $v = $this->run($sql, $args)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $sql, array $args = []): int
    {
        $this->run($sql, $args);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Run a closure inside a transaction, rolling back on any exception.
     * Nested calls join the outer transaction rather than starting a second
     * one, which PDO would reject.
     */
    public function transact(callable $fn)
    {
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->pdo->beginTransaction();
        try {
            $out = $fn($this);
            $this->pdo->commit();
            return $out;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        $v = $this->value(
            'SELECT value_text FROM settings WHERE key_name = ?',
            [$key]
        );
        return $v === null ? $default : (string) $v;
    }

    public function setSetting(string $key, string $value): void
    {
        // Portable upsert: no ON CONFLICT (SQLite dialect) and no ON DUPLICATE
        // KEY (MySQL dialect), both of which would tie this to one engine.
        $exists = $this->value('SELECT 1 FROM settings WHERE key_name = ?', [$key]);
        if ($exists === null) {
            $this->run(
                'INSERT INTO settings (key_name, value_text) VALUES (?, ?)',
                [$key, $value]
            );
        } else {
            $this->run(
                'UPDATE settings SET value_text = ? WHERE key_name = ?',
                [$value, $key]
            );
        }
    }

    /** URL-safe random token for manage links, unsubscribes and logins. */
    public static function token(int $bytes = 24): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
