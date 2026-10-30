<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Database;

use Parcely\Database\Row;
use PHPUnit\Framework\TestCase;

final class RowTest extends TestCase
{
    public function testTypedAccessToPdoPgsqlValues(): void
    {
        // What pdo_pgsql returns: int for integer columns, string for float8, JSON as string.
        $row = new Row(['id' => 1755256604, 'label' => 'st. 1868/26', 'lon' => '15.3710490001', 'none' => null, 'numbers' => '[3,5]']);

        self::assertSame(1755256604, $row->int('id'));
        self::assertSame('st. 1868/26', $row->string('label'));
        self::assertSame(15.3710490001, $row->float('lon'));
        self::assertNull($row->nullableInt('none'));
        self::assertNull($row->nullableString('none'));
        self::assertSame([3, 5], $row->intList('numbers'));
    }

    public function testWrongTypeFails(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Column "id" is not int');

        (new Row(['id' => '12']))->int('id');
    }

    public function testNullWhereValueIsRequiredFails(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        (new Row(['label' => null]))->string('label');
    }

    public function testMissingColumnFails(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('no column "nazev"');

        (new Row(['kod' => 1]))->string('nazev');
    }

    public function testIntListRejectsOtherValues(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        (new Row(['numbers' => '["a"]']))->intList('numbers');
    }
}
