<?php
declare(strict_types=1);

namespace Rebound;

/**
 * Works out which appointment slots a client may request.
 *
 * The rule this whole class exists to enforce: a slot is offered only if the
 * ENTIRE appointment, plus the turnaround buffer after it, fits inside a
 * working window and touches nothing else. Offering a start time and only
 * discovering at booking that the session would run past closing is the
 * classic version of this bug, and it is why the check is on the interval
 * rather than on the start instant.
 *
 * A 'requested' appointment blocks its slot just as firmly as a confirmed one.
 * She approves every booking by hand, so if pending requests did not block,
 * three people could request the same Tuesday at 2pm and two of them would
 * have to be turned down by a human. Holding the slot while she decides is
 * both kinder and less work.
 */
final class Availability
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    private function intSetting(string $key, int $default): int
    {
        $v = $this->db->setting($key);
        return $v === null ? $default : (int) $v;
    }

    /** Statuses that occupy the room. */
    public const BLOCKING = ['requested', 'confirmed'];

    /**
     * Bookable slots for one service, grouped by local calendar day.
     *
     * @return array<string, list<array{start:string,end:string,label:string}>>
     *         keyed by 'Y-m-d' in studio-local time
     */
    public function slotsForService(int $serviceId, int $daysAhead = 21, ?int $staffId = null): array
    {
        $service = $this->db->one(
            'SELECT * FROM services WHERE id = ? AND active = 1',
            [$serviceId]
        );
        if ($service === null) {
            return [];
        }

        $staffId ??= $this->defaultStaffId();
        if ($staffId === null) {
            return [];
        }

        $durationMin = (int) $service['duration_min'];
        $bufferMin   = $this->intSetting('buffer_min', 15);
        $granMin     = $this->intSetting('slot_granularity_min', 30);
        $noticeHours = $this->intSetting('min_notice_hours', 12);
        $maxAdvance  = $this->intSetting('max_advance_days', 90);

        $daysAhead = min($daysAhead, $maxAdvance);

        $now      = Clock::nowUtc();
        $earliest = $now->modify("+{$noticeHours} hours");

        $rules     = $this->rulesByWeekday($staffId);
        $busy      = $this->busyIntervals($staffId, $now, $daysAhead);
        $blackouts = $this->blackoutIntervals($staffId, $now, $daysAhead);
        $occupied  = array_merge($busy, $blackouts);

        $out = [];
        $today = Clock::toStudio($now);

        for ($d = 0; $d <= $daysAhead; $d++) {
            $day     = $today->modify("+{$d} days");
            $ymd     = $day->format('Y-m-d');
            $weekday = (int) $day->format('w');

            foreach ($rules[$weekday] ?? [] as $rule) {
                $windowStart = Clock::fromStudioLocal($ymd, $rule['start_local']);
                $windowEnd   = Clock::fromStudioLocal($ymd, $rule['end_local']);

                // Overnight windows are not a thing she works, and silently
                // producing an empty day is better than looping to the heat
                // death of the universe on bad data.
                if ($windowEnd <= $windowStart) {
                    continue;
                }

                $cursor = $windowStart;
                while (true) {
                    $apptEnd = $cursor->modify("+{$durationMin} minutes");
                    // The buffer must fit inside the working window too: she
                    // needs the turnaround before she can leave, not just
                    // before the next client.
                    $needEnd = $apptEnd->modify("+{$bufferMin} minutes");
                    if ($needEnd > $windowEnd) {
                        break;
                    }

                    if ($cursor >= $earliest
                        && !$this->collides($cursor, $needEnd, $occupied, $bufferMin)) {
                        $startSql = $cursor->format(Clock::SQL);
                        $out[$ymd][] = [
                            'start' => $startSql,
                            'end'   => $apptEnd->format(Clock::SQL),
                            'label' => Clock::humanTime($startSql),
                        ];
                    }

                    $cursor = $cursor->modify("+{$granMin} minutes");
                }
            }
        }

        return $out;
    }

    /**
     * Does [start, end) touch anything already occupied?
     *
     * Both sides are padded by the buffer, so a new session cannot begin the
     * instant an existing one ends. Half-open comparison throughout: an
     * appointment ending at 3:00 and one starting at 3:00 do not overlap.
     */
    private function collides(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $occupied,
        int $bufferMin
    ): bool {
        foreach ($occupied as $iv) {
            $bStart = $iv['start'];
            $bEnd   = $iv['end'];
            if ($iv['buffered']) {
                $bEnd = $bEnd->modify("+{$bufferMin} minutes");
            }
            if ($start < $bEnd && $bStart < $end) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int, list<array{start_local:string,end_local:string}>> */
    private function rulesByWeekday(int $staffId): array
    {
        $rows = $this->db->all(
            'SELECT weekday, start_local, end_local
               FROM availability_rules
              WHERE staff_id = ?
           ORDER BY weekday, start_local',
            [$staffId]
        );
        $by = [];
        foreach ($rows as $r) {
            $by[(int) $r['weekday']][] = $r;
        }
        return $by;
    }

    /** Existing appointments that hold the room. */
    private function busyIntervals(int $staffId, \DateTimeImmutable $from, int $daysAhead): array
    {
        $to = $from->modify('+' . ($daysAhead + 1) . ' days');
        $ph = implode(',', array_fill(0, count(self::BLOCKING), '?'));
        $rows = $this->db->all(
            "SELECT starts_at, ends_at
               FROM appointments
              WHERE staff_id = ?
                AND status IN ($ph)
                AND ends_at   >= ?
                AND starts_at <= ?",
            array_merge([$staffId], self::BLOCKING,
                        [$from->format(Clock::SQL), $to->format(Clock::SQL)])
        );
        return array_map(static fn(array $r): array => [
            'start'    => Clock::fromSql($r['starts_at']),
            'end'      => Clock::fromSql($r['ends_at']),
            'buffered' => true,
        ], $rows);
    }

    /**
     * Blackouts are NOT buffered - if she blocks 12:00 to 13:00 for lunch she
     * means exactly that, and padding it would quietly eat another slot on
     * each side.
     */
    private function blackoutIntervals(int $staffId, \DateTimeImmutable $from, int $daysAhead): array
    {
        $to = $from->modify('+' . ($daysAhead + 1) . ' days');
        $rows = $this->db->all(
            'SELECT starts_at, ends_at
               FROM blackouts
              WHERE staff_id = ?
                AND ends_at   >= ?
                AND starts_at <= ?',
            [$staffId, $from->format(Clock::SQL), $to->format(Clock::SQL)]
        );
        return array_map(static fn(array $r): array => [
            'start'    => Clock::fromSql($r['starts_at']),
            'end'      => Clock::fromSql($r['ends_at']),
            'buffered' => false,
        ], $rows);
    }

    public function defaultStaffId(): ?int
    {
        $v = $this->db->value(
            'SELECT id FROM staff
              WHERE bookable = 1 AND active = 1
           ORDER BY sort_order, id LIMIT 1'
        );
        return $v === null ? null : (int) $v;
    }

    /**
     * Authoritative re-check at booking time, inside the caller's transaction.
     *
     * slotsForService() decides what to display; this decides what is true.
     * Between the two, someone else may have taken the slot - so this is the
     * one that actually protects the calendar, and it deliberately re-reads
     * rather than trusting anything the browser sent back.
     */
    public function isStillFree(int $staffId, string $startSql, string $endSql): bool
    {
        $bufferMin = $this->intSetting('buffer_min', 15);
        $start = Clock::fromSql($startSql);
        $end   = Clock::fromSql($endSql);

        $lock = $this->db->supportsForUpdate() ? ' FOR UPDATE' : '';
        $ph   = implode(',', array_fill(0, count(self::BLOCKING), '?'));

        // Widen the scan by the buffer on both sides so a neighbouring
        // appointment whose buffer would overlap us is caught as well.
        $scanFrom = $start->modify("-{$bufferMin} minutes")->format(Clock::SQL);
        $scanTo   = $end->modify("+{$bufferMin} minutes")->format(Clock::SQL);

        $rows = $this->db->all(
            "SELECT starts_at, ends_at
               FROM appointments
              WHERE staff_id = ?
                AND status IN ($ph)
                AND ends_at   > ?
                AND starts_at < ?" . $lock,
            array_merge([$staffId], self::BLOCKING, [$scanFrom, $scanTo])
        );
        foreach ($rows as $r) {
            $bStart = Clock::fromSql($r['starts_at']);
            $bEnd   = Clock::fromSql($r['ends_at'])->modify("+{$bufferMin} minutes");
            if ($start < $bEnd && $bStart < $end) {
                return false;
            }
        }

        $black = $this->db->all(
            'SELECT 1 FROM blackouts
              WHERE staff_id = ? AND ends_at > ? AND starts_at < ?',
            [$staffId, $startSql, $endSql]
        );
        if ($black !== []) {
            return false;
        }

        return $this->withinWorkingHours($staffId, $start, $end, $bufferMin);
    }

    /**
     * A slot posted back by a browser is untrusted input. Without this, a
     * crafted request could book 3am on a Sunday.
     */
    private function withinWorkingHours(
        int $staffId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        int $bufferMin
    ): bool {
        $localStart = Clock::toStudio($start);
        $ymd        = $localStart->format('Y-m-d');
        $weekday    = (int) $localStart->format('w');
        $needEnd    = $end->modify("+{$bufferMin} minutes");

        $rules = $this->db->all(
            'SELECT start_local, end_local FROM availability_rules
              WHERE staff_id = ? AND weekday = ?',
            [$staffId, $weekday]
        );
        foreach ($rules as $rule) {
            $ws = Clock::fromStudioLocal($ymd, $rule['start_local']);
            $we = Clock::fromStudioLocal($ymd, $rule['end_local']);
            if ($start >= $ws && $needEnd <= $we) {
                return true;
            }
        }
        return false;
    }
}
