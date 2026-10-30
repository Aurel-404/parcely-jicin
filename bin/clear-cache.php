<?php

declare(strict_types=1);

/*
 * Deletes the tile cache of previous VFR data sets (var/tiles/{vfr_date}); keeps the imported one.
 * Run inside the php container after a monthly import:
 *
 *   docker compose exec php php bin/clear-cache.php [--all]
 *
 * --all deletes the current data set too: needed after a change of the code that renders tiles or boundaries,
 * because the cache is versioned by the data, not by the code.
 */

use Parcely\Cli\ClearCacheCommand;
use Parcely\Cli\Console;
use Parcely\Config;
use Parcely\Database\Database;
use Parcely\Repository\MetaRepository;
use Parcely\Tile\TileCache;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = Config::fromEnvironment(getenv());
$command = new ClearCacheCommand(
    new TileCache($config->varDirectory . '/tiles'),
    new MetaRepository(Database::fromConfig($config)),
    new Console(),
);

exit($command->run(isset(getopt('', ['all'])['all'])));
