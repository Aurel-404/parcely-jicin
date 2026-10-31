<?php

declare(strict_types=1);

/*
 * Tile benchmark: renders the parcel tiles over the district without the cache (TileRenderer directly)
 * and prints render time and size per zoom. Run inside the php container:
 *
 *   docker compose exec php php bin/bench.php [--zoom=12-16] [--sample=300]
 */

use Parcely\Cli\BenchCommand;
use Parcely\Cli\Console;
use Parcely\Config;
use Parcely\Database\Database;
use Parcely\Tile\ParcelTiles;
use Parcely\Tile\TileCoordinate;
use Parcely\Tile\TileRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

// gzip_comp_level in docker/nginx/default.conf: the size a browser actually downloads.
const NGINX_GZIP_LEVEL = 5;

$console = new Console();
$options = getopt('', ['zoom:', 'sample:', 'help']);
if (isset($options['help'])) {
    $console->info('Usage: php bin/bench.php [--zoom=12-16] [--sample=300]');
    exit(0);
}

$zoomRange = is_string($options['zoom'] ?? null) ? $options['zoom'] : '12-16';
$sample = is_string($options['sample'] ?? null) ? (int) $options['sample'] : 300;
if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $zoomRange, $m) !== 1 || (int) $m[1] > (int) $m[2]
    || (int) $m[2] > TileCoordinate::MAX_ZOOM || $sample < 1) {
    $console->error('--zoom must be a range like 12-16 (up to ' . TileCoordinate::MAX_ZOOM . '), --sample a positive number');
    exit(2);
}

$config = Config::fromEnvironment(getenv());
$command = new BenchCommand(new TileRenderer(Database::fromConfig($config)), $console);

exit($command->run(ParcelTiles::districtExtent(), range((int) $m[1], (int) $m[2]), $sample, NGINX_GZIP_LEVEL));
