<?php

declare(strict_types=1);

namespace Parcely\Download;

final readonly class CurlHttpClient implements HttpClient
{
    public const string USER_AGENT = 'parcely-jicin/1.0 (RUIAN VFR downloader; PHP curl)';

    public function __construct(
        private string $userAgent = self::USER_AGENT,
        private int $connectTimeoutSeconds = 15,
        private int $timeoutSeconds = 600,
    ) {}

    public function status(string $url): int
    {
        return $this->request($url, [CURLOPT_NOBODY => true]);
    }

    public function download(string $url, string $path): int
    {
        $file = fopen($path, 'wb');
        if ($file === false) {
            throw new \RuntimeException(sprintf('Cannot write %s', $path));
        }

        try {
            return $this->request($url, [CURLOPT_FILE => $file]);
        } finally {
            fclose($file);
        }
    }

    /**
     * @param array<int, mixed> $options
     */
    private function request(string $url, array $options): int
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransferException(sprintf('Cannot initialize request to %s', $url));
        }

        curl_setopt_array($handle, $options + [
            CURLOPT_USERAGENT => $this->userAgent,
            // The old domain vdp.cuzk.cz redirects to vdp.cuzk.gov.cz; allow redirects, but only over HTTPS.
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_REDIR_PROTOCOLS_STR => 'https',
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        if (curl_exec($handle) === false) {
            throw new TransferException(sprintf('%s: %s', $url, curl_error($handle)));
        }

        return curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    }
}
