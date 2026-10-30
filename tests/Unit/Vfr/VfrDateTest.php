<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Vfr;

use Parcely\Vfr\VfrDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VfrDateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function defaultDates(): iterable
    {
        yield 'end of October' => ['2026-10-29', '20260930'];
        yield 'start of March (February has 28 days)' => ['2026-03-02', '20260228'];
        yield 'leap year' => ['2028-03-01', '20280229'];
        yield 'January goes to previous year' => ['2026-01-15', '20251231'];
    }

    #[DataProvider('defaultDates')]
    public function testDefaultIsLastDayOfPreviousMonth(string $today, string $expected): void
    {
        self::assertSame($expected, VfrDate::defaultMonthly(new \DateTimeImmutable($today)));
    }

    public function testMonthlyFallbacksAreFirstOfMonthThenMonthEarlier(): void
    {
        self::assertSame(
            ['20260930', '20261001', '20260831'],
            VfrDate::monthlyCandidates(new \DateTimeImmutable('2026-10-29')),
        );
    }

    public function testCodeListCandidatesAreFirstWeekOfThisAndPreviousMonth(): void
    {
        $candidates = VfrDate::codeListCandidates(new \DateTimeImmutable('2026-01-30'));

        self::assertCount(14, $candidates);
        self::assertSame(['20260101', '20260107'], [$candidates[0], $candidates[6]]);
        self::assertSame(['20251201', '20251207'], [$candidates[7], $candidates[13]]);
    }

    public function testParseAcceptsRealDate(): void
    {
        self::assertSame('20260930', VfrDate::parse('20260930'));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield ['20260931'];
        yield ['2026-09-30'];
        yield ['202609'];
        yield ['abc'];
    }

    #[DataProvider('invalidDates')]
    public function testParseRejectsInvalidDate(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VfrDate::parse($value);
    }
}
