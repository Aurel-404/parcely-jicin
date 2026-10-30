<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Vfr;

use Parcely\Vfr\Municipality;
use Parcely\Vfr\MunicipalityCsv;
use PHPUnit\Framework\TestCase;

final class MunicipalityCsvTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'obce');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testRoundTrip(): void
    {
        $municipalities = [new Municipality(572659, 'Jičín'), new Municipality(573248, 'Nová Paka')];

        MunicipalityCsv::write($this->path, $municipalities);

        self::assertSame("kod;nazev\n572659;Jičín\n573248;\"Nová Paka\"\n", file_get_contents($this->path));
        self::assertEquals($municipalities, MunicipalityCsv::read($this->path));
    }

    public function testRejectsUnexpectedHeader(): void
    {
        file_put_contents($this->path, "code,name\n572659,Jičín\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected header "kod;nazev"');

        MunicipalityCsv::read($this->path);
    }

    public function testRejectsInvalidRowWithLineNumber(): void
    {
        file_put_contents($this->path, "kod;nazev\n572659;Jičín\nabc;Hořice\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(':3: expected "kod;nazev"');

        MunicipalityCsv::read($this->path);
    }
}
