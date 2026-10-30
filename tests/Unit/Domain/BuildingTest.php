<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Domain;

use Parcely\Domain\Building;
use Parcely\Domain\CodeValue;
use PHPUnit\Framework\TestCase;

final class BuildingTest extends TestCase
{
    public function testDesignationDependsOnBuildingType(): void
    {
        self::assertSame('č. p. 540', self::building(1, [540])->designation());
        self::assertSame('č. ev. 12', self::building(2, [12])->designation());
        self::assertSame('č. p. 3, 5', self::building(1, [3, 5])->designation());
        self::assertNull(self::building(3, [])->designation(), 'budova bez č.p. i č.ev.');
        self::assertNull(self::building(1, [])->designation(), 'number missing in the data');
    }

    public function testJson(): void
    {
        $building = new Building(39791998, new CodeValue(3, 'budova bez č.p. i č.ev.'), [], new CodeValue(18, 'garáž'));

        self::assertSame(
            '{"id":39791998,"oznaceni":null,"typ":{"kod":3,"nazev":"budova bez č.p. i č.ev."},"cisla_domovni":[],"zpusob_vyuziti":{"kod":18,"nazev":"garáž"}}',
            json_encode($building, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @param list<int> $numbers
     */
    private static function building(int $type, array $numbers): Building
    {
        return new Building(1, new CodeValue($type, 'typ'), $numbers, null);
    }
}
