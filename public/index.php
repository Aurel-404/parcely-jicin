<?php

declare(strict_types=1);

use Parcely\Application;
use Parcely\Config;
use Parcely\Database\Database;
use Parcely\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = Config::fromEnvironment(getenv());
$app = new Application($config, Database::fromConfig($config));

$app->handle(Request::fromGlobals())->send();
