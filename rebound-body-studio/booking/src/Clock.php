<?php
declare(strict_types=1);

namespace Rebound;

/**
 * The only place in this codebase permitted to convert between timezones.
 *
 * Everything stored is UTC. Everything shown to a human is America/Phoenix.
 * Arizona does not observe daylight saving, so that offset is a constant -07:00
 * and the usual booking-software disasters - a 2am slot that happens twice in
 * November, or not at all in March - cannot occur here.
 *
 * It is still written against a named timezone rather than a hardcoded -7, so
 * that if she ever moves or takes on a second location the fix is one constant
 * and not an archaeology exercise.
 */
final class Clock
{
    public const STUDIO_TZ = 'America/Phoenix';
    public const SQL = 'Y-m-d H:i:s';

    public static function utc(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }

    public static function studio(): \DateTimeZone
    {
        return new \DateTimeZone(self::STUDIO_TZ);
    }

    /** Current instant, UTC, as stored in the database. */
    public static function nowSql(): string
    {
        return (new \DateTimeImmutable('now', self::utc()))->format(self::SQL);
    }

    public static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', self::utc());
    }

    /** Parse a stored UTC string back into an instant. */
    public static function fromSql(string $sql): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat(self::SQL, $sql, self::utc());
        if ($d === false) {
            throw new \InvalidArgumentException("Not a stored timestamp: {$sql}");
        }
        return $d;
    }

    /**
     * Build a UTC instant from a local date and wall-clock time.
     * This is how "Tuesday at 9am, her time" becomes something storable.
     */
    public static function fromStudioLocal(string $ymd, string $hm): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $ymd . ' ' . $hm,
            self::studio()
        );
        if ($d === false) {
            throw new \InvalidArgumentException("Bad local datetime: {$ymd} {$hm}");
        }
        return $d->setTimezone(self::utc());
    }

    public static function toStudio(\DateTimeImmutable $utc): \DateTimeImmutable
    {
        return $utc->setTimezone(self::studio());
    }

    /** "Tuesday 16 September, 2:30 PM" - what goes in an email to a client. */
    public static function human(string $sqlUtc): string
    {
        return self::toStudio(self::fromSql($sqlUtc))->format('l j F, g:i A');
    }

    /** "2:30 PM" for a slot button. */
    public static function humanTime(string $sqlUtc): string
    {
        return self::toStudio(self::fromSql($sqlUtc))->format('g:i A');
    }

    /** "Tue 16 Sep" for a day heading. */
    public static function humanDay(string $sqlUtc): string
    {
        return self::toStudio(self::fromSql($sqlUtc))->format('D j M');
    }

    /** Local calendar date of a stored instant, for grouping slots by day. */
    public static function studioYmd(string $sqlUtc): string
    {
        return self::toStudio(self::fromSql($sqlUtc))->format('Y-m-d');
    }

    /**
     * Timezone-correct calendar format for an .ics attachment. Written as a
     * floating UTC stamp with the trailing Z, which every calendar client
     * understands without needing a VTIMEZONE block.
     */
    public static function ics(string $sqlUtc): string
    {
        return self::fromSql($sqlUtc)->format('Ymd\THis\Z');
    }
}
