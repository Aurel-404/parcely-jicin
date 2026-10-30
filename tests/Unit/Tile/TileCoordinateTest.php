<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Tile;

use Parcely\Tile\TileCoordinate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TileCoordinateTest extends TestCase
{
    public function testParsesPathSegments(): void
    {
        $tile = TileCoordinate::fromStrings('14', '8892', '5526');

        self::assertSame([14, 8892, 5526], [$tile->z, $tile->x, $tile->y]);
        self::assertSame('14/8892/5526', $tile->path());
    }

    public function testAcceptsBoundaryValues(): void
    {
        self::assertSame(0, TileCoordinate::of(0, 0, 0)->z);
        self::assertSame(65535, TileCoordinate::of(16, 65535, 65535)->x);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidCoordinates(): iterable
    {
        yield 'zoom above maximum' => ['17', '0', '0'];
        yield 'x outside the zoom' => ['14', '16384', '0'];
        yield 'y outside the zoom' => ['0', '0', '1'];
        yield 'negative' => ['14', '-1', '0'];
        yield 'not a number' => ['14', 'abc', '0'];
        yield 'decimal' => ['14', '1.5', '0'];
        yield 'empty' => ['', '0', '0'];
        yield 'too many digits' => ['14', '0000001', '0'];
    }

    #[DataProvider('invalidCoordinates')]
    public function testRejectsInvalidCoordinates(string $z, string $x, string $y): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TileCoordinate::fromStrings($z, $x, $y);
    }

    public function testEnvelopeMatchesPostgisTileEnvelope(): void
    {
        // SELECT ST_TileEnvelope(14, 8892, 5526) in PostGIS 3.6.
        $envelope = TileCoordinate::of(14, 8892, 5526)->envelope();

        self::assertEqualsWithDelta(1712189.4335879497, $envelope->minX, 1e-6);
        self::assertEqualsWithDelta(6518549.772159832, $envelope->minY, 1e-6);
        self::assertEqualsWithDelta(1714635.4184930734, $envelope->maxX, 1e-6);
        self::assertEqualsWithDelta(6520995.757064957, $envelope->maxY, 1e-6);
    }

    public function testZoomZeroCoversTheWorld(): void
    {
        $envelope = TileCoordinate::of(0, 0, 0)->envelope();

        self::assertSame([-20037508.342789244, -20037508.342789244, 20037508.342789244, 20037508.342789244], [
            $envelope->minX, $envelope->minY, $envelope->maxX, $envelope->maxY,
        ]);
    }
}
