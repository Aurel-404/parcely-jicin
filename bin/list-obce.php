<?php

declare(strict_types=1);

/*
 * Generates data/obce_okres_jicin.csv (the municipalities of the Jičín district) from the state-wide
 * VFR file ST_UZSZ. Run inside the php container:
 *
 *   docker compose exec php php bin/list-obce.php [--date=YYYYMMDD]
 */

use Parcely\Cli\Console;
use Parcely\Cli\ListMunicipalitiesCommand;
use Parcely\Config;
use Parcely\Download\CurlHttpClient;
use Parcely\Download\FileDownloader;
use Parcely\Vfr\AdministrativeUnitsReader;
use Parcely\Vfr\VfrDate;
use Parcely\Vfr\VfrLocator;
use Parcely\Vfr\VfrSource;

require dirname(__DIR__) . '/vendor/autoload.php';

const DISTRICT_NAME = 'Jičín';
// Official number of municipalities in the district (ČSÚ: ORP Jičín, Hořice and Nová Paka).
const EXPECTED_MUNICIPALITIES = 111;

$console = new Console();
$options = getopt('', ['date:', 'help']);
if (isset($options['help'])) {
    $console->info('Usage: php bin/list-obce.php [--date=YYYYMMDD]');
    exit(0);
}

try {
    $date = is_string($options['date'] ?? null) ? VfrDate::parse($options['date']) : null;
} catch (InvalidArgumentException $e) {
    $console->error($e->getMessage());
    exit(2);
}

$config = Config::fromEnvironment(getenv());
$http = new CurlHttpClient();
$source = new VfrSource($config->vfrBaseUrl);
$command = new ListMunicipalitiesCommand(
    new VfrLocator($http, $source),
    $source,
    new FileDownloader($http, dirname(__DIR__) . '/data/raw'),
    new AdministrativeUnitsReader(),
    $console,
);

exit($command->run(
    new DateTimeImmutable('today'),
    $date,
    DISTRICT_NAME,
    EXPECTED_MUNICIPALITIES,
    dirname(__DIR__) . '/data/obce_okres_jicin.csv',
));
