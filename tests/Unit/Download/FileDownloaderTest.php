<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Download;

use Parcely\Download\DownloadFailed;
use Parcely\Download\FileDownloader;
use Parcely\Download\RemoteFile;
use Parcely\Tests\Double\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class FileDownloaderTest extends TestCase
{
    private const string URL = 'https://example.test/vymenny_format/soucasna/20260930_OB_572659_UKSH.xml.zip';

    private string $directory;

    /** @var list<float> */
    private array $sleeps = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/parcely-download-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testDownloadsValidZipUnderFinalName(): void
    {
        $http = (new FakeHttpClient())->respond(200, self::zip());

        $result = $this->downloader($http)->fetch(RemoteFile::fromUrl(self::URL));

        self::assertFalse($result->skipped);
        self::assertSame($this->directory . '/20260930_OB_572659_UKSH.xml.zip', $result->path);
        self::assertSame(strlen(self::zip()), $result->bytes);
        self::assertSame(['20260930_OB_572659_UKSH.xml.zip'], $this->files());
    }

    public function testSkipsExistingFileWithoutRequest(): void
    {
        file_put_contents($this->directory . '/20260930_OB_572659_UKSH.xml.zip', 'already here');
        $http = new FakeHttpClient();

        $result = $this->downloader($http)->fetch(RemoteFile::fromUrl(self::URL));

        self::assertTrue($result->skipped);
        self::assertSame(12, $result->bytes);
        self::assertSame([], $http->requests);
    }

    public function testRetriesServerErrorsAndNetworkFailuresWithBackoff(): void
    {
        $http = (new FakeHttpClient())
            ->respond(503, 'busy')
            ->failTransfer('Connection reset')
            ->respond(200, self::zip());

        $result = $this->downloader($http)->fetch(RemoteFile::fromUrl(self::URL));

        self::assertFalse($result->skipped);
        self::assertCount(3, $http->requests);
        self::assertSame([1.0, 2.0, 0.3], $this->sleeps, 'backoff 1 s, 2 s, then the polite pause');
    }

    public function testNotFoundIsNotRetriedAndLeavesNoFile(): void
    {
        $http = (new FakeHttpClient())->respond(404, '<html>404</html>');

        try {
            $this->downloader($http)->fetch(RemoteFile::fromUrl(self::URL));
            self::fail('Expected DownloadFailed');
        } catch (DownloadFailed $e) {
            self::assertStringContainsString('HTTP 404', $e->getMessage());
        }
        self::assertCount(1, $http->requests);
        self::assertSame([], $this->files());
    }

    public function testInvalidArchiveIsRetriedThenFails(): void
    {
        $http = new FakeHttpClient();
        for ($i = 0; $i < 4; $i++) {
            $http->respond(200, 'truncated, not a zip');
        }

        $this->expectException(DownloadFailed::class);
        $this->expectExceptionMessage('not a valid ZIP archive');
        try {
            $this->downloader($http)->fetch(RemoteFile::fromUrl(self::URL));
        } finally {
            self::assertCount(4, $http->requests, '1 attempt + 3 retries');
            self::assertSame([], $this->files(), 'no .part file left behind');
        }
    }

    private function downloader(FakeHttpClient $http): FileDownloader
    {
        return new FileDownloader($http, $this->directory, sleep: function (float $seconds): void {
            $this->sleeps[] = $seconds;
        });
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        return array_map(basename(...), glob($this->directory . '/*') ?: []);
    }

    private static function zip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('20260930_OB_572659_UKSH.xml', '<vf:VymennyFormat/>');
        $zip->close();
        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }
}
