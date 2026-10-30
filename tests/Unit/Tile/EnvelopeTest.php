<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Tile;

use Parcely\Tile\Envelope;
use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    public function testIntersectsOverlappingAndTouching(): void
    {
        $a = new Envelope(0, 0, 10, 10);

        self::assertTrue($a->intersects(new Envelope(5, 5, 15, 15)));
        self::assertTrue($a->intersects(new Envelope(10, 0, 20, 10)), 'shared edge');
        self::assertFalse($a->intersects(new Envelope(10.5, 0, 20, 10)));
        self::assertFalse($a->intersects(new Envelope(0, -20, 10, -0.5)));
    }

    public function testContainsAndExpand(): void
    {
        $a = new Envelope(0, 0, 10, 10);

        self::assertTrue($a->contains(new Envelope(1, 1, 9, 9)));
        self::assertFalse($a->contains(new Envelope(1, 1, 11, 9)));
        self::assertTrue($a->expand(1)->contains(new Envelope(-1, -1, 11, 11)));
    }

    public function testToLonLatMatchesPostgis(): void
    {
        // ST_Transform(ST_SetSRID(ST_MakePoint(…), 3857), 4326) in PostGIS 3.6 for the district extent.
        $lonLat = (new Envelope(1681395, 6494089, 1752343, 6541545))->toLonLat();

        self::assertEqualsWithDelta([15.104228, 50.275293, 15.741565, 50.546965], $lonLat, 1e-6);
    }

    public function testRejectsInvertedEnvelope(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Envelope(10, 0, 0, 10);
    }
}
