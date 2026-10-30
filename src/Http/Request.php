<?php

declare(strict_types=1);

namespace Parcely\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $query only scalar query parameters (arrays like ?a[]=1 are dropped)
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
    ) {}

    public static function fromGlobals(): self
    {
        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        $query = [];
        foreach ($_GET as $name => $value) {
            if (is_string($value)) {
                $query[(string) $name] = $value;
            }
        }

        return new self(
            method: strtoupper($method),
            path: is_string($path) && $path !== '' ? $path : '/',
            query: $query,
        );
    }

    public function query(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }
}
