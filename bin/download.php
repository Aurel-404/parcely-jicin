<?php

declare(strict_types=1);

/*
 * Downloads the RÚIAN VFR files for the Jičín district into data/raw (Docker volume raw-data):
 * OB_{code}_UKSH for every municipality in data/obce_okres_jicin.csv, ST_UKSH and ST_UCIS.
 * Run inside the php container:
 *
 *   docker compose exec php php bin/download.php [--date=YYYYMMDD] [--ucis-url=URL]
 */

use Parcely\Cli\Console;
use Parcely\Cli\DownloadCommand;
use Parcely\Config;
use Parcely\Download\CurlHttpClient;
use Parcely\Download\FileDownloader;
use Parcely\Vfr\VfrDate;
use Parcely\Vfr\VfrLocator;
use Parcely\Vfr\VfrSource;

require dirname(__DIR__) . '/vendor/autoload.php';

$console = new Console();
$options = getopt('', ['date:', 'ucis-url:', 'help']);
if (isset($options['help'])) {
    $console->info(<<<'USAGE'
        Usage: php bin/download.php [--date=YYYYMMDD] [--ucis-url=URL]
          --date      monthly data set (default: last day of the previous month, with fallbacks)
          --ucis-url  explicit URL of the ST_UCIS code list file (default: newest one found)
        USAGE);
    exit(0);
}

try {
    $date = is_string($options['date'] ?? null) ? VfrDate::parse($options['date']) : null;
} catch (InvalidArgumentException $e) {
    $console->error($e->getMessage());
    exit(2);
}
$codeListUrl = is_string($options['ucis-url'] ?? null) ? $options['ucis-url'] : null;

$config = Config::fromEnvironment(getenv());
$http = new CurlHttpClient();
$source = new VfrSource($config->vfrBaseUrl);
$command = new DownloadCommand(
    new VfrLocator($http, $source),
    $source,
    new FileDownloader($http, dirname(__DIR__) . '/data/raw'),
    $console,
);

exit($command->run(new DateTimeImmutable('today'), $date, $codeListUrl, dirname(__DIR__) . '/data/obce_okres_jicin.csv'));
