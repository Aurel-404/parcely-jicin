<?php

declare(strict_types=1);

namespace Parcely\Cli;

use Parcely\Tile\Envelope;
use Parcely\Tile\TileCoordinate;
use Parcely\Tile\TileRenderer;

/**
 * Renders tiles over the district without the cache and reports render time and size per zoom.
 * The numbers decide ParcelTiles::MIN_ZOOM.
 */
final readonly class BenchCommand
{
    /** A zoom with more tiles than this is measured on a random sample. */
    private const int FULL_LIMIT = 2500;

    public function __construct(
        private TileRenderer $renderer,
        private Console $console,
    ) {}

    /**
     * @param list<int> $zooms
     * @param int $sample tiles measured for a zoom over FULL_LIMIT
     * @param int $gzipLevel compression level of nginx (gzip_comp_level), for the transferred size
     */
    public function run(Envelope $area, array $zooms, int $sample, int $gzipLevel): int
    {
        // The first query of a connection loads PostGIS into the database backend; not part of a tile's time.
        $this->renderer->parcels(TileCoordinate::of(14, 8892, 5526));
        mt_srand(20260930);

        $this->console->info(sprintf('%4s %7s %7s %8s %8s %8s %8s %8s %10s', ...[
            'zoom', 'tiles', 'data', 'p50 ms', 'p95 ms', 'max ms', 'avg kB', 'max kB', 'max kB gz',
        ]));
        foreach ($zooms as $zoom) {
            $tiles = TileCoordinate::covering($area, $zoom);
            $total = count($tiles);
            if ($total > self::FULL_LIMIT) {
                shuffle($tiles);
                $tiles = array_slice($tiles, 0, $sample);
            }

            $times = $sizes = [];
            $maxGzip = 0;
            foreach ($tiles as $tile) {
                $started = hrtime(true);
                $mvt = $this->renderer->parcels($tile);
                $elapsed = (hrtime(true) - $started) / 1e6;
                // Statistics only over tiles with parcels: empty tiles at the corners of the box are trivial.
                if ($mvt !== '') {
                    $times[] = $elapsed;
                    $sizes[] = strlen($mvt);
                    $maxGzip = max($maxGzip, strlen((string) gzencode($mvt, $gzipLevel)));
                }
            }

            sort($times);
            $this->console->info(sprintf(
                '%4d %7s %7d %8.1f %8.1f %8.1f %8.1f %8.1f %10.1f',
                $zoom,
                count($tiles) < $total ? sprintf('%d/%d', count($tiles), $total) : (string) $total,
                count($times),
                self::percentile($times, 0.5),
                self::percentile($times, 0.95),
                $times === [] ? 0.0 : max($times),
                $sizes === [] ? 0.0 : array_sum($sizes) / count($sizes) / 1000,
                $sizes === [] ? 0.0 : max($sizes) / 1000,
                $maxGzip / 1000,
            ));
        }

        return 0;
    }

    /**
     * Nearest-rank percentile of sorted values.
     *
     * @param list<float> $sorted
     */
    private static function percentile(array $sorted, float $p): float
    {
        return $sorted === [] ? 0.0 : $sorted[max(0, (int) ceil($p * count($sorted)) - 1)];
    }
}
