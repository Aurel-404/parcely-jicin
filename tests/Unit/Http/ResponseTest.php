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

    public function testWithHeaderReturnsNewInstance(): void
    {
        $original = new Response();
        $changed = $original->withHeader('Cache-Control', 'no-store');

        self::assertSame([], $original->headers);
        self::assertSame(['Cache-Control' => 'no-store'], $changed->headers);
    }
}
