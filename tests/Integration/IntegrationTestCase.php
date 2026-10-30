<?php

declare(strict_types=1);

namespace Parcely\Tests\Integration;

use Parcely\Config;
use Parcely\Database\Database;
use PHPUnit\Framework\TestCase;

/**
 * Tests against the real PostGIS database from the environment (inside the php container).
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static function config(): Config
    {
        if (getenv('DB_HOST') === false) {
            self::markTestSkipped('DB_HOST is not set - run the tests inside the php container.');
        }

        return Config::fromEnvironment(getenv());
    }

    protected static function database(): Database
    {
        return Database::fromConfig(self::config());
    }

    /**
     * Skips the test until bin/import.sh has run.
     */
    protected static function requireImportedData(Database $db): void
    {
        if ($db->fetchValue("SELECT to_regclass('public.parcel')") === null) {
            self::markTestSkipped('No imported data - run bin/import.sh.');
        }
    }
}
