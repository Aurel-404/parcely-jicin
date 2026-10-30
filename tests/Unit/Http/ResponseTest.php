<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Http;

use Parcely\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testJsonKeepsUnicodeAndSlashesReadable(): void
    {
        $response = Response::json(['ku' => 'Jičín', 'url' => 'https://nahlizenidokn.cuzk.cz/'], 201);

        self::assertSame(201, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame('{"ku":"Jičín","url":"https://nahlizenidokn.cuzk.cz/"}', $response->body);
    }

    public function testFileResponseSendsTheFile(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tile');
        file_put_contents($path, 'mvt');

        $response = Response::file($path, 'application/vnd.mapbox-vector-tile', ['ETag' => '"x"'])->withHeader('X-Cache', 'HIT');
        ob_start();
        $response->send();
        $sent = ob_get_clean();
        unlink($path);

        self::assertSame('mvt', $sent);
        self::assertSame(
            ['X-Cache' => 'HIT', 'Content-Type' => 'application/vnd.mapbox-vector-tile', 'Content-Length' => '3', 'ETag' => '"x"'],
            $response->headers,
        );
    }

    public function testNotModifiedHasNoBody(): void
    {
        $response = Response::notModified(['ETag' => '"x"']);

        self::assertSame(304, $response->status);
        self::assertSame('', $response->body);
        self::assertNull($response->file);
    }

    public function testWithHeaderReturnsNewInstance(): void
    {
        $original = new Response();
        $changed = $original->withHeader('Cache-Control', 'no-store');

        self::assertSame([], $original->headers);
        self::assertSame(['Cache-Control' => 'no-store'], $changed->headers);
    }
}
