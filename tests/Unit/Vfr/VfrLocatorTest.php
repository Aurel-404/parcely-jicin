<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Vfr;

use Parcely\Tests\Double\FakeHttpClient;
use Parcely\Vfr\VfrLocator;
use Parcely\Vfr\VfrSource;
use PHPUnit\Framework\TestCase;

final class VfrLocatorTest extends TestCase
{
    private const string BASE = 'https://vdp.cuzk.gov.cz/vymenny_format';

    public function testMonthlyDateIsFirstCandidateWithPublishedStateFile(): void
    {
        $http = new FakeHttpClient([self::BASE . '/soucasna/20260831_ST_UKSH.xml.zip' => 200]);

        $date = (new VfrLocator($http, new VfrSource()))->findMonthlyDate(['20260930', '20261001', '20260831']);

        self::assertSame('20260831', $date);
        self::assertCount(3, $http->requests);
    }

    public function testMonthlyDateIsNullWhenNothingIsPublished(): void
    {
        self::assertNull((new VfrLocator(new FakeHttpClient(), new VfrSource()))->findMonthlyDate(['20260930']));
    }

    public function testCodeListsAreLookedUpInSpecialDirectory(): void
    {
        $http = new FakeHttpClient([self::BASE . '/specialni/20261003_ST_UCIS.xml.zip' => 200]);

        $file = (new VfrLocator($http, new VfrSource()))->findCodeLists(['20261001', '20261002', '20261003', '20261004']);

        self::assertNotNull($file);
        self::assertSame('20261003_ST_UCIS.xml.zip', $file->name);
        self::assertCount(3, $http->requests, 'stops at the first hit');
    }
}
