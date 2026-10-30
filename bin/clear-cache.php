<?php

declare(strict_types=1);

/*
 * Deletes the tile cache of previous VFR data sets (var/tiles/{vfr_date}); keeps the imported one.
 * Run inside the php container after a monthly import:
 *
 *   docker compose exec php php bin/clear-cache.php
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

exit($command->run());
