<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Tile;

use Parcely\Database\Database;
use Parcely\Tile\ParcelTiles;
use Parcely\Tile\TileCache;
use Parcely\Tile\TileCoordinate;
use Parcely\Tile\TileRenderer;
use PHPUnit\Framework\TestCase;

final class ParcelTilesTest extends TestCase
{
    /** z16 tile in the centre of Jičín. */
    private const int JICIN_X = 35569;
    private const int JICIN_Y = 22104;

    private ParcelTiles $tiles;

    protected function setUp(): void
    {
        // The database connects lazily and is unreachable: the decision must not need it.
        $db = new Database('pgsql:host=127.0.0.1;port=1;dbname=x', 'x', 'x');
        $this->tiles = new ParcelTiles(new TileRenderer($db), new TileCache(sys_get_temp_dir()));
    }

    public function testTilesOverJicinMayContainParcels(): void
    {
        self::assertTrue($this->tiles->mayContainParcels(self::jicinAt(16)));
        self::assertTrue($this->tiles->mayContainParcels(self::jicinAt(ParcelTiles::MIN_ZOOM)));
    }

    public function testBelowMinimumZoomIsEmpty(): void
    {
        self::assertFalse($this->tiles->mayContainParcels(self::jicinAt(ParcelTiles::MIN_ZOOM - 1)));
    }

    public function testOutsideDistrictIsEmpty(): void
    {
        self::assertFalse($this->tiles->mayContainParcels(TileCoordinate::of(14, 0, 0)));
        self::assertFalse($this->tiles->mayContainParcels(TileCoordinate::of(14, 8848, 5549)), 'Praha');
    }

    public function testWestEdgeOfDistrict(): void
    {
        // The district starts at x = 1 681 395 m. At z16 a tile is 611.5 m wide: tile 35517 spans
        // 1 681 003 – 1 681 615 m, tile 35516 ends 392 m west of the district (far beyond the 9.6 m buffer).
        self::assertTrue($this->tiles->mayContainParcels(TileCoordinate::of(16, 35517, 22108)));
        self::assertFalse($this->tiles->mayContainParcels(TileCoordinate::of(16, 35516, 22108)));
    }

    private static function jicinAt(int $zoom): TileCoordinate
    {
        $shift = 16 - $zoom;

        return TileCoordinate::of($zoom, self::JICIN_X >> $shift, self::JICIN_Y >> $shift);
    }
}
