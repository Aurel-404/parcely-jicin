<?php

declare(strict_types=1);

namespace Parcely\Download;

interface HttpClient
{
    /**
     * Sends a HEAD request and returns the HTTP status code.
     *
     * @throws TransferException when no HTTP response arrives (DNS, timeout, connection reset ...)
     */
    public function status(string $url): int;

    /**
     * Streams the response body into $path (overwriting it) and returns the HTTP status code.
     *
     * @throws TransferException when no complete HTTP response arrives
     */
    public function download(string $url, string $path): int;
}
