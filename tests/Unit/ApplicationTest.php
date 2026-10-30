<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit;

use Parcely\Application;
use Parcely\Config;
use Parcely\Database\Database;
use Parcely\Http\Request;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testUnknownPathGivesJson404(): void
    {
        $response = $this->app()->handle(new Request('GET', '/nope'));

        self::assertSame(404, $response->status);
        self::assertSame('{"error":"Not found"}', $response->body);
    }

    public function testWrongMethodGivesJson405(): void
    {
        $response = $this->app()->handle(new Request('DELETE', '/api/health'));

        self::assertSame(405, $response->status);
        self::assertSame('GET', $response->headers['Allow']);
    }

    public function testHealthReports503WhenDatabaseIsUnreachable(): void
    {
        // Port 1 on localhost refuses connections immediately - no waiting for a timeout.
        $db = new Database('pgsql:host=127.0.0.1;port=1;dbname=x;connect_timeout=1', 'x', 'x');
        $this->expectErrorLog();

        $response = $this->app($db)->handle(new Request('GET', '/api/health'));

        self::assertSame(503, $response->status);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertStringContainsString('"database":"unavailable"', $response->body);
    }

    private function app(?Database $db = null): Application
    {
        $config = new Config('prod', 'unused', 5432, 'unused', 'unused', 'unused');

        return new Application($config, $db ?? new Database('pgsql:host=unused', 'unused', 'unused'));
    }
}
