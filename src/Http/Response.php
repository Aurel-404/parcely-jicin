<?php

declare(strict_types=1);

namespace Parcely\Http;

final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public string $body = '',
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers, $body);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, [$name => $value] + $this->headers, $this->body);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
