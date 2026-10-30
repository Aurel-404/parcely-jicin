<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Http;

use Parcely\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private const string ETAG = '"20260930-14-8892-5526"';

    public function testHeaderNamesAreCaseInsensitive(): void
    {
        $request = new Request('GET', '/', headers: ['if-none-match' => self::ETAG]);

        self::assertSame(self::ETAG, $request->header('If-None-Match'));
        self::assertNull($request->header('Accept'));
    }

    public function testMatchesStrongWeakListedAndWildcardEtags(): void
    {
        self::assertTrue(self::withIfNoneMatch(self::ETAG)->hasEtag(self::ETAG));
        // nginx turns the ETag weak when it gzips the response; the browser sends it back that way.
        self::assertTrue(self::withIfNoneMatch('W/' . self::ETAG)->hasEtag(self::ETAG));
        self::assertTrue(self::withIfNoneMatch('"other", ' . self::ETAG)->hasEtag(self::ETAG));
        self::assertTrue(self::withIfNoneMatch('*')->hasEtag(self::ETAG));
    }

    public function testDoesNotMatchOtherOrMissingEtag(): void
    {
        self::assertFalse(self::withIfNoneMatch('"20260831-14-8892-5526"')->hasEtag(self::ETAG));
        self::assertFalse((new Request('GET', '/'))->hasEtag(self::ETAG));
    }

    private static function withIfNoneMatch(string $value): Request
    {
        return new Request('GET', '/', headers: ['if-none-match' => $value]);
    }
}
