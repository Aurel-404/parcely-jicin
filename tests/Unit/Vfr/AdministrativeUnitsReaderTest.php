<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Vfr;

use Parcely\Vfr\AdministrativeUnitsReader;
use Parcely\Vfr\Municipality;
use PHPUnit\Framework\TestCase;

final class AdministrativeUnitsReaderTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../Fixtures/st_uzsz_excerpt.xml';

    public function testReadsDistrictsAndTheirMunicipalities(): void
    {
        $units = (new AdministrativeUnitsReader())->read(self::FIXTURE);

        self::assertSame(3604, $units->districtCode('Jičín'));
        self::assertEquals(
            [new Municipality(572659, 'Jičín'), new Municipality(572926, 'Hořice')],
            $units->municipalitiesOf(3604),
        );
        self::assertEquals([new Municipality(569089, 'Maleč')], $units->municipalitiesOf(3601));
        self::assertSame([], $units->municipalitiesOf(3605), 'Náchod has no municipality in the excerpt');
    }

    public function testMunicipalityWithoutDistrictIsSkipped(): void
    {
        // The excerpt contains Praha (554782), which belongs to no district.
        $units = (new AdministrativeUnitsReader())->read(self::FIXTURE);

        $all = array_merge($units->municipalitiesOf(3604), $units->municipalitiesOf(3601));
        self::assertNotContains(554782, array_map(static fn(Municipality $m): int => $m->code, $all));
    }

    public function testUnknownDistrictNameFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected exactly one district named "Jičínsko"');

        (new AdministrativeUnitsReader())->read(self::FIXTURE)->districtCode('Jičínsko');
    }
}
