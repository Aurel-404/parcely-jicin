<?php

declare(strict_types=1);

/*
 * Loads code lists (druh pozemku, způsob využití, druh číslování, …) from ST_UCIS into tables cis_*.
 * Run inside the php container before bin/import.sh:
 *
 *   docker compose exec php php bin/import-codelists.php [--file=data/raw/YYYYMMDD_ST_UCIS.xml.zip]
 */

use Parcely\Cli\Console;
use Parcely\Cli\ImportCodeListsCommand;
use Parcely\Config;
use Parcely\Database\Database;
use Parcely\Import\CodeListWriter;
use Parcely\Vfr\CodeListReader;

require dirname(__DIR__) . '/vendor/autoload.php';

$console = new Console();
$options = getopt('', ['file:', 'help']);
if (isset($options['help'])) {
    $console->info('Usage: php bin/import-codelists.php [--file=PATH]   (default: newest data/raw/*_ST_UCIS.xml.zip)');
    exit(0);
}

$command = new ImportCodeListsCommand(
    new CodeListReader(),
    new CodeListWriter(Database::fromConfig(Config::fromEnvironment(getenv()))),
    $console,
);

exit($command->run(
    dirname(__DIR__) . '/data/raw',
    is_string($options['file'] ?? null) ? $options['file'] : null,
));
