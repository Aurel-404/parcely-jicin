<?php

declare(strict_types=1);

namespace Parcely\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $query only scalar query parameters (arrays like ?a[]=1 are dropped)
     * @param array<string, string> $headers lower-case names, e.g. "if-none-match"
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $headers = [],
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

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (is_string($value) && str_starts_with((string) $name, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $name, 5)))] = $value;
            }
        }

        return new self(
            method: strtoupper($method),
            path: is_string($path) && $path !== '' ? $path : '/',
            query: $query,
            headers: $headers,
        );
    }

    public function query(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Whether the client already has this version (If-None-Match). nginx turns a strong ETag into a weak one
     * (W/"…") when it gzips the response, so the weak form matches too.
     */
    public function hasEtag(string $etag): bool
    {
        $header = $this->header('If-None-Match');
        if ($header === null) {
            return false;
        }
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
                return true;
            }
        }

        return false;
    }
}
