<?php

declare(strict_types=1);

namespace Parcely\Vfr;

/**
 * Dates used in VFR file names (YYYYMMDD).
 */
final class VfrDate
{
    private const int CODE_LIST_PUBLISH_DAYS = 7;

    /**
     * The monthly full copy is dated with the last day of the previous month (20260930 for October 2026).
     */
    public static function defaultMonthly(\DateTimeImmutable $today): string
    {
        return self::monthlyCandidates($today)[0];
    }

    /**
     * Dates to try for the monthly full copy, most likely first:
     * last day of the previous month, the 1st of this month (naming seen in older examples, e.g. 20190301),
     * and the last day of the month before (when this month's set is not published yet).
     *
     * @return list<string>
     */
    public static function monthlyCandidates(\DateTimeImmutable $today): array
    {
        $firstOfMonth = $today->modify('first day of this month');

        return [
            $firstOfMonth->modify('-1 day')->format('Ymd'),
            $firstOfMonth->format('Ymd'),
            $firstOfMonth->modify('-1 month')->modify('-1 day')->format('Ymd'),
        ];
    }

    /**
     * Code lists (ST_UCIS) are published in the first days of a month (seen: 20260703, 20260803, 20261003).
     * Tries days 1–7 of this month, then of the previous month.
     *
     * @return list<string>
     */
    public static function codeListCandidates(\DateTimeImmutable $today): array
    {
        $candidates = [];
        foreach ([$today, $today->modify('first day of previous month')] as $month) {
            $first = $month->modify('first day of this month');
            for ($day = 0; $day < self::CODE_LIST_PUBLISH_DAYS; $day++) {
                $candidates[] = $first->modify(sprintf('+%d days', $day))->format('Ymd');
            }
        }

        return $candidates;
    }

    /**
     * Validates a date given on the command line.
     */
    public static function parse(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Ymd', $value);
        if ($date === false || $date->format('Ymd') !== $value) {
            throw new \InvalidArgumentException(sprintf('Invalid date "%s", expected YYYYMMDD.', $value));
        }

        return $value;
    }
}
