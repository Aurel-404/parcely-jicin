<?php

declare(strict_types=1);

namespace Parcely\Tests\Integration;

use Parcely\Application;
use Parcely\Config;
use Parcely\Database\Database;
use Parcely\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real PostGIS database from the environment (inside the php container).
 */
final class HealthTest extends TestCase
{
    public function testHealthReportsPostgisVersion(): void
    {
        if (getenv('DB_HOST') === false) {
            self::markTestSkipped('DB_HOST is not set - run the tests inside the php container.');
        }
        $config = Config::fromEnvironment(getenv());

        $response = (new Application($config, Database::fromConfig($config)))->handle(new Request('GET', '/api/health'));

        self::assertSame(200, $response->status, $response->body);
        $body = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('ok', $body['status']);
        self::assertIsString($body['postgis']);
        self::assertStringStartsWith('3.', $body['postgis']);
    }
}
