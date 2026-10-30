<?php

declare(strict_types=1);

namespace Parcely\Tests\Integration;

use Parcely\Config;
use Parcely\Database\Database;
use PHPUnit\Framework\TestCase;

/**
 * Checks the result of bin/import.sh in the real database; skipped until the data is imported.
 */
final class ImportedDataTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        if (getenv('DB_HOST') === false) {
            self::markTestSkipped('DB_HOST is not set - run the tests inside the php container.');
        }
        $this->db = Database::fromConfig(Config::fromEnvironment(getenv()));
        if ($this->db->fetchValue("SELECT to_regclass('public.parcel')") === null) {
            self::markTestSkipped('No imported data - run bin/import.sh.');
        }
    }

    public function testWholeDistrictIsImported(): void
    {
        self::assertSame(1, $this->db->fetchValue('SELECT count(*) FROM okres'));
        self::assertSame(111, $this->db->fetchValue('SELECT count(*) FROM obec'), 'Not all municipalities - was it a trial import with --obec?');
        $vfrDate = $this->db->fetchValue("SELECT value FROM meta WHERE key = 'vfr_date'");
        self::assertIsString($vfrDate);
        self::assertMatchesRegularExpression('/^\d{8}$/', $vfrDate);
    }

    public function testParcelGeometriesAreValidWebMercator(): void
    {
        self::assertSame(0, $this->db->fetchValue('SELECT count(*) FROM parcel WHERE NOT ST_IsValid(geom) OR ST_IsEmpty(geom)'));
        self::assertSame(3857, $this->db->fetchValue('SELECT DISTINCT ST_SRID(geom) FROM parcel'));
    }

    public function testBuildingPlotsAreLabelledWithPrefix(): void
    {
        self::assertSame(0, $this->db->fetchValue(
            "SELECT count(*) FROM parcel WHERE (druh_cislovani_kod = 1) <> (label LIKE 'st. %')",
        ));
    }

    public function testTileQueryUsesSpatialIndex(): void
    {
        // A z16 tile in the centre of Jičín.
        $plan = $this->db->fetchValue(
            'EXPLAIN (FORMAT JSON) SELECT id FROM parcel WHERE geom && ST_TileEnvelope(16, 35569, 22104)',
        );

        self::assertIsString($plan);
        self::assertStringContainsString('parcel_geom_gix', $plan);
    }
}
