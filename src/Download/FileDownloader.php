<?php

declare(strict_types=1);

namespace Parcely\Download;

/**
 * Idempotent, resumable download of ZIP archives into one directory:
 * - an existing non-empty file is skipped (files get their final name only after validation),
 * - data goes to "<name>.part" and is renamed after it proved to be a valid ZIP,
 * - network errors, 5xx/429 and broken archives are retried with exponential backoff; other statuses (404) are not.
 */
final readonly class FileDownloader
{
    /** @var \Closure(float): void */
    private \Closure $sleep;

    /**
     * @param \Closure(float): void|null $sleep waits the given number of seconds (replaceable in tests)
     */
    public function __construct(
        private HttpClient $http,
        private string $targetDirectory,
        private int $maxRetries = 3,
        private float $backoffSeconds = 1.0,
        private float $pauseSeconds = 0.3,
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
    }

    /**
     * @throws DownloadFailed
     */
    public function fetch(RemoteFile $file): DownloadResult
    {
        $path = $this->targetDirectory . '/' . $file->name;
        $size = is_file($path) ? filesize($path) : false;
        if ($size !== false && $size > 0) {
            return new DownloadResult($path, $size, skipped: true);
        }

        $partPath = $path . '.part';
        for ($attempt = 0; ; $attempt++) {
            $error = $this->attempt($file->url, $partPath);
            if ($error === null) {
                rename($partPath, $path);
                ($this->sleep)($this->pauseSeconds);

                return new DownloadResult($path, (int) filesize($path), skipped: false);
            }

            if (is_file($partPath)) {
                unlink($partPath);
            }
            if (!$error['retry'] || $attempt >= $this->maxRetries) {
                throw new DownloadFailed(sprintf('%s: %s', $file->url, $error['message']));
            }
            ($this->sleep)($this->backoffSeconds * 2 ** $attempt);
        }
    }

    /**
     * @return array{message: string, retry: bool}|null null on success
     */
    private function attempt(string $url, string $partPath): ?array
    {
        try {
            $status = $this->http->download($url, $partPath);
        } catch (TransferException $e) {
            return ['message' => $e->getMessage(), 'retry' => true];
        }

        if ($status !== 200) {
            return ['message' => sprintf('HTTP %d', $status), 'retry' => $status >= 500 || $status === 429];
        }
        if (!self::isValidZip($partPath)) {
            return ['message' => 'downloaded file is not a valid ZIP archive', 'retry' => true];
        }

        return null;
    }

    private static function isValidZip(string $path): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CHECKCONS | \ZipArchive::RDONLY) !== true) {
            return false;
        }
        $hasEntries = $zip->count() > 0;
        $zip->close();

        return $hasEntries;
    }
}
