<?php

declare(strict_types=1);

namespace Parcely\Tests\Integration;

use Parcely\Application;
use Parcely\Database\Database;
use Parcely\Http\Request;

final class HealthTest extends IntegrationTestCase
{
    public function testHealthReportsPostgisVersion(): void
    {
        $config = self::config();

        $response = (new Application($config, Database::fromConfig($config)))->handle(new Request('GET', '/api/health'));

        self::assertSame(200, $response->status, $response->body);
        $body = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('ok', $body['status']);
        self::assertIsString($body['postgis']);
        self::assertStringStartsWith('3.', $body['postgis']);
    }
}
