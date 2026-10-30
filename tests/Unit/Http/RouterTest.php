<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Http;

use Parcely\Http\HttpException;
use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/api/health', static fn(): Response => new Response(body: 'health'));
        $this->router->get(
            '/tiles/parcels/{z:\d+}/{x:\d+}/{y:\d+}.pbf',
            static fn(Request $request, array $params): Response => new Response(body: implode('/', $params)),
        );
        $this->router->get(
            '/api/parcels/{id}',
            static fn(Request $request, array $params): Response => new Response(body: $params['id']),
        );
    }

    public function testMatchesStaticRoute(): void
    {
        self::assertSame('health', $this->dispatch('GET', '/api/health')->body);
    }

    public function testExtractsNamedParameters(): void
    {
        self::assertSame('14/8912/5543', $this->dispatch('GET', '/tiles/parcels/14/8912/5543.pbf')->body);
    }

    public function testDefaultPlaceholderMatchesOneSegment(): void
    {
        self::assertSame('123', $this->dispatch('GET', '/api/parcels/123')->body);

        $this->expectNotFound();
        $this->dispatch('GET', '/api/parcels/123/extra');
    }

    public function testRejectsParameterNotMatchingItsPattern(): void
    {
        $this->expectNotFound();
        $this->dispatch('GET', '/tiles/parcels/14/abc/5543.pbf');
    }

    public function testDefaultPlaceholderBeforeLiteralSuffix(): void
    {
        $router = new Router();
        $router->get('/tiles/{z}/{x}/{y}.pbf', static fn(Request $request, array $params): Response => new Response(body: implode(',', $params)));

        // Anything up to ".pbf" is captured, so the controller can answer 400 instead of 404 for "abc".
        self::assertSame('14,abc,5526', $router->dispatch(new Request('GET', '/tiles/14/abc/5526.pbf'))->body);
    }

    public function testLiteralDotIsNotARegexWildcard(): void
    {
        $this->expectNotFound();
        $this->dispatch('GET', '/tiles/parcels/14/8912/5543xpbf');
    }

    public function testHeadIsServedByGetRoute(): void
    {
        self::assertSame(200, $this->dispatch('HEAD', '/api/health')->status);
    }

    public function testWrongMethodGives405WithAllowHeader(): void
    {
        try {
            $this->dispatch('POST', '/api/health');
            self::fail('Expected HttpException');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
            self::assertSame(['Allow' => 'GET'], $e->headers);
        }
    }

    private function dispatch(string $method, string $path): Response
    {
        return $this->router->dispatch(new Request($method, $path));
    }

    private function expectNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Not found');
    }
}
