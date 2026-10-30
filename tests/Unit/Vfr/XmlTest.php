<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Vfr;

use Parcely\Vfr\Xml;
use PHPUnit\Framework\TestCase;

final class XmlTest extends TestCase
{
    private string $zipPath;

    protected function setUp(): void
    {
        $this->zipPath = sys_get_temp_dir() . '/' . uniqid('vfr', true) . '.xml.zip';
    }

    protected function tearDown(): void
    {
        if (is_file($this->zipPath)) {
            unlink($this->zipPath);
        }
    }

    public function testZipEntryUriReadsTheOnlyXmlFile(): void
    {
        $this->createZip(['20260930_ST_UZSZ.xml' => '<a>ok</a>']);

        $uri = Xml::zipEntryUri($this->zipPath);

        self::assertSame('zip://' . $this->zipPath . '#20260930_ST_UZSZ.xml', $uri);
        self::assertSame('<a>ok</a>', file_get_contents($uri));
    }

    public function testZipEntryUriRejectsArchiveWithMoreFiles(): void
    {
        $this->createZip(['a.xml' => '<a/>', 'b.xml' => '<b/>']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected a ZIP with one XML file');

        Xml::zipEntryUri($this->zipPath);
    }

    public function testIntChildRejectsNonNumber(): void
    {
        $document = new \DOMDocument();
        $document->loadXML('<x:Item xmlns:x="urn:test"><x:Kod>12a</x:Kod></x:Item>');
        $item = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $item);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('x:Item/Kod is not a number: "12a"');

        Xml::intChild($item, 'urn:test', 'Kod');
    }

    /**
     * @param array<string, string> $entries
     */
    private function createZip(array $entries): void
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }
}
