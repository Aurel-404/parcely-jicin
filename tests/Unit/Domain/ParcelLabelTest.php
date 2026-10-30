<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Domain;

use Parcely\Domain\ParcelLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParcelLabelTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, int, int|null, string}>
     */
    public static function validLabels(): iterable
    {
        yield 'land plot' => ['567', false, 567, null, '567'];
        yield 'land plot with subdivision' => ['567/2', false, 567, 2, '567/2'];
        yield 'building plot' => ['st. 123/4', true, 123, 4, 'st. 123/4'];
        yield 'no space after st.' => ['st.123', true, 123, null, 'st. 123'];
        yield 'no dot, capital' => ['St 1868/26', true, 1868, 26, 'st. 1868/26'];
        yield 'spaces around' => ['  st.  12 / 3 ', true, 12, 3, 'st. 12/3'];
        yield 'leading zeros' => ['007/01', false, 7, 1, '7/1'];
    }

    #[DataProvider('validLabels')]
    public function testParsesAndFormats(string $text, bool $buildingPlot, int $number, ?int $subdivision, string $formatted): void
    {
        $label = ParcelLabel::parse($text);

        self::assertSame($buildingPlot, $label->buildingPlot);
        self::assertSame($number, $label->number);
        self::assertSame($subdivision, $label->subdivision);
        self::assertSame($formatted, $label->format());
        self::assertSame($formatted, (string) $label);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLabels(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['abc'];
        yield 'zero' => ['0'];
        yield 'zero subdivision' => ['12/0'];
        yield 'two slashes' => ['12/3/4'];
        yield 'missing subdivision' => ['12/'];
        yield 'other prefix' => ['p. č. 12'];
        yield 'negative' => ['-12'];
        yield 'too long' => ['1234567890'];
    }

    #[DataProvider('invalidLabels')]
    public function testRejectsInvalidInput(string $text): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ParcelLabel::parse($text);
    }

    public function testFormatMatchesLabelsFromImport(): void
    {
        // db/transform.sql: CASE WHEN druhcislovanikod = 1 THEN 'st. ' ELSE '' END || kmenovecislo || '/' || poddeleni
        self::assertSame('st. 1868/26', (new ParcelLabel(true, 1868, 26))->format());
        self::assertSame('740/11', (new ParcelLabel(false, 740, 11))->format());
    }
}
