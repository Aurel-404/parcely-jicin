<?php

declare(strict_types=1);

namespace Parcely\Tests\Double;

use Parcely\Download\HttpClient;
use Parcely\Download\TransferException;

/**
 * Scripted HTTP client: HEAD answers come from a URL => status map, downloads from a queue.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<string> "HEAD <url>" / "GET <url>" in call order */
    public array $requests = [];

    /** @var list<array{status: int, body: string}|string> a string entry = TransferException message */
    private array $downloads = [];

    /**
     * @param array<string, int> $statuses HEAD status per URL, anything else is 404
     */
    public function __construct(private readonly array $statuses = []) {}

    public function respond(int $status, string $body = ''): self
    {
        $this->downloads[] = ['status' => $status, 'body' => $body];

        return $this;
    }

    public function failTransfer(string $message): self
    {
        $this->downloads[] = $message;

        return $this;
    }

    public function status(string $url): int
    {
        $this->requests[] = 'HEAD ' . $url;

        return $this->statuses[$url] ?? 404;
    }

    public function download(string $url, string $path): int
    {
        $this->requests[] = 'GET ' . $url;
        $next = array_shift($this->downloads) ?? throw new \LogicException('Unexpected download of ' . $url);
        if (is_string($next)) {
            throw new TransferException($next);
        }
        file_put_contents($path, $next['body']);

        return $next['status'];
    }
}
