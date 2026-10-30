<?php

declare(strict_types=1);

namespace Parcely\Http;

final readonly class Response
{
    /**
     * @param array<string, string> $headers
     * @param string|null $file path sent with readfile() instead of $body (cached tiles)
     */
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public string $body = '',
        public ?string $file = null,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function file(string $path, string $contentType, array $headers = []): self
    {
        $size = filesize($path);
        if ($size === false) {
            throw new \RuntimeException(sprintf('Cannot read %s', $path));
        }

        return new self(200, ['Content-Type' => $contentType, 'Content-Length' => (string) $size] + $headers, file: $path);
    }

    /**
     * 304: the client's cached copy (If-None-Match) is current.
     *
     * @param array<string, string> $headers ETag and Cache-Control of the full response
     */
    public static function notModified(array $headers): self
    {
        return new self(304, $headers);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, [$name => $value] + $this->headers, $this->body, $this->file);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->file !== null) {
            readfile($this->file);
        } else {
            echo $this->body;
        }
    }
}
