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

    public function testEmptyTilesAreAnsweredWithoutDatabase(): void
    {
        // The default database of app() does not exist; a query would end in 500.
        foreach (['/tiles/parcels/12/2223/1381.pbf' => 'below minimum zoom', '/tiles/parcels/14/0/0.pbf' => 'outside the district'] as $path => $case) {
            $response = $this->app()->handle(new Request('GET', $path));

            self::assertSame(200, $response->status, $case);
            self::assertSame('', $response->body, $case);
            self::assertSame('application/vnd.mapbox-vector-tile', $response->headers['Content-Type']);
        }
    }

    public function testInvalidInputGives400WithoutDatabase(): void
    {
        foreach (['/tiles/parcels/17/0/0.pbf', '/tiles/parcels/14/abc/1.pbf', '/api/parcels/abc', '/api/parcels/0', '/api/search?ku=Ji%C4%8D%C3%ADn', '/api/ku'] as $uri) {
            $path = (string) parse_url($uri, PHP_URL_PATH);
            parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
            /** @var array<string, string> $query */
            $response = $this->app()->handle(new Request('GET', $path, $query));

            self::assertSame(400, $response->status, $uri);
            self::assertStringStartsWith('{"error":', $response->body);
        }
    }

    public function testSearchRejectsInvalidUtf8(): void
    {
        $response = $this->app()->handle(new Request('GET', '/api/ku', ['q' => "Ji\xC4"]));

        self::assertSame(400, $response->status);
    }

    private function app(?Database $db = null): Application
    {
        $config = new Config('prod', 'unused', 5432, 'unused', 'unused', 'unused');

        return new Application($config, $db ?? new Database('pgsql:host=unused', 'unused', 'unused'));
    }
}
