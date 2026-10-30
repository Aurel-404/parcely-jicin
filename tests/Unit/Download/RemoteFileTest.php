<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Download;

use Parcely\Download\RemoteFile;
use PHPUnit\Framework\TestCase;

final class RemoteFileTest extends TestCase
{
    public function testNameIsTakenFromUrlPath(): void
    {
        $file = RemoteFile::fromUrl('https://vdp.cuzk.gov.cz/vymenny_format/specialni/20261003_ST_UCIS.xml.zip?x=1');

        self::assertSame('20261003_ST_UCIS.xml.zip', $file->name);
    }

    public function testRejectsUrlWithoutZipFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RemoteFile::fromUrl('https://vdp.cuzk.gov.cz/vymenny_format/specialni/');
    }
}
