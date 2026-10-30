<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Vfr;

use Parcely\Vfr\VfrSource;
use PHPUnit\Framework\TestCase;

final class VfrSourceTest extends TestCase
{
    public function testBuildsVerifiedUrls(): void
    {
        $source = new VfrSource();

        self::assertSame(
            'https://vdp.cuzk.gov.cz/vymenny_format/soucasna/20260930_OB_572659_UKSH.xml.zip',
            $source->municipality('20260930', 572659)->url,
        );
        self::assertSame(
            'https://vdp.cuzk.gov.cz/vymenny_format/soucasna/20260930_ST_UZSZ.xml.zip',
            $source->stateBasic('20260930')->url,
        );
        self::assertSame(
            'https://vdp.cuzk.gov.cz/vymenny_format/soucasna/20260930_ST_UKSH.xml.zip',
            $source->stateBoundaries('20260930')->url,
        );
        self::assertSame(
            'https://vdp.cuzk.gov.cz/vymenny_format/specialni/20261003_ST_UCIS.xml.zip',
            $source->codeLists('20261003')->url,
        );
    }

    public function testCustomBaseUrlWithTrailingSlash(): void
    {
        $file = (new VfrSource('https://mirror.test/vfr/'))->stateBasic('20260930');

        self::assertSame('https://mirror.test/vfr/soucasna/20260930_ST_UZSZ.xml.zip', $file->url);
        self::assertSame('20260930_ST_UZSZ.xml.zip', $file->name);
    }
}
