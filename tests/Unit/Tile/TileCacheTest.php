<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Tile;

use Parcely\Tile\TileCache;
use PHPUnit\Framework\TestCase;

final class TileCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('tiles', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            exec('rm -rf ' . escapeshellarg($this->directory));
        }
    }

    public function testRendersOnMissAndServesTheFileOnHit(): void
    {
        $cache = new TileCache($this->directory);
        $renders = 0;
        $render = static function () use (&$renders): string {
            $renders++;

            return 'tile-bytes';
        };

        $miss = $cache->remember('20260930', 'parcels/14/8892/5526.pbf', $render);
        $hit = $cache->remember('20260930', 'parcels/14/8892/5526.pbf', $render);

        self::assertFalse($miss->hit);
        self::assertTrue($hit->hit);
        self::assertSame(1, $renders);
        self::assertSame($this->directory . '/20260930/parcels/14/8892/5526.pbf', $hit->path);
        self::assertSame('tile-bytes', file_get_contents($hit->path));
        self::assertSame([], glob($this->directory . '/20260930/parcels/14/8892/*.tmp'), 'temporary file left behind');
    }

    public function testNewDataSetUsesItsOwnFolder(): void
    {
        $cache = new TileCache($this->directory);
        $cache->remember('20260831', 'boundaries.geojson', static fn(): string => 'old');

        $new = $cache->remember('20260930', 'boundaries.geojson', static fn(): string => 'new');

        self::assertFalse($new->hit);
        self::assertSame('new', file_get_contents($new->path));
    }

    public function testEmptyTileIsCachedToo(): void
    {
        $cache = new TileCache($this->directory);
        $cache->remember('20260930', 'parcels/16/0/0.pbf', static fn(): string => '');

        self::assertTrue($cache->remember('20260930', 'parcels/16/0/0.pbf', static fn(): string => 'x')->hit);
    }

    public function testClearExceptDeletesOnlyOlderDataSets(): void
    {
        $cache = new TileCache($this->directory);
        foreach (['20260731', '20260831', '20260930'] as $date) {
            $cache->remember($date, 'parcels/14/1/2.pbf', static fn(): string => $date);
        }
        mkdir($this->directory . '/not-a-data-set');

        $deleted = $cache->clearExcept('20260930');

        self::assertSame(['20260731', '20260831'], $deleted);
        self::assertDirectoryExists($this->directory . '/20260930');
        self::assertDirectoryDoesNotExist($this->directory . '/20260831');
        self::assertDirectoryExists($this->directory . '/not-a-data-set', 'unknown folders are left alone');
    }

    public function testClearAllDeletesTheCurrentDataSetToo(): void
    {
        $cache = new TileCache($this->directory);
        $cache->remember('20260930', 'boundaries.geojson', static fn(): string => '{}');

        self::assertSame(['20260930'], $cache->clearExcept(null));
        self::assertDirectoryDoesNotExist($this->directory . '/20260930');
    }

    public function testRejectsPathTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TileCache($this->directory))->remember('20260930', '../../etc/passwd', static fn(): string => '');
    }

    public function testRejectsInvalidDataSetDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TileCache($this->directory))->remember('../x', 'boundaries.geojson', static fn(): string => '');
    }
}
