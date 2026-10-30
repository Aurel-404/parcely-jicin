<?php

declare(strict_types=1);

namespace Parcely\Http;

/**
 * Minimal router with placeholders: "/tiles/{z:\d+}/{x:\d+}/{y:\d+}.pbf".
 * A placeholder without a pattern matches one path segment ([^/]+).
 * Patterns must not contain braces (no {n} quantifiers).
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: \Closure(Request, array<string, string>): Response}> */
    private array $routes = [];

    /**
     * @param \Closure(Request, array<string, string>): Response $handler
     */
    public function get(string $pattern, \Closure $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /**
     * @param \Closure(Request, array<string, string>): Response $handler
     */
    public function add(string $method, string $pattern, \Closure $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => self::compile($pattern),
            'handler' => $handler,
        ];
    }

    /**
     * @throws HttpException 404 when no route matches the path, 405 when it matches with another method
     */
    public function dispatch(Request $request): Response
    {
        // HEAD is answered by GET routes; PHP itself omits the body for HEAD requests.
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, $params);
        }

        if ($allowedMethods !== []) {
            throw HttpException::methodNotAllowed(array_values(array_unique($allowedMethods)));
        }

        throw HttpException::notFound();
    }

    private static function compile(string $pattern): string
    {
        preg_match_all(
            '/\{(\w+)(?::([^{}]+))?\}/',
            $pattern,
            $placeholders,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
        );

        $regex = '';
        $offset = 0;
        foreach ($placeholders as $placeholder) {
            [$token, $position] = $placeholder[0];
            $regex .= preg_quote(substr($pattern, $offset, $position - $offset), '#');
            $regex .= sprintf('(?P<%s>%s)', $placeholder[1][0], $placeholder[2][0] ?? '[^/]+');
            $offset = $position + strlen((string) $token);
        }
        $regex .= preg_quote(substr($pattern, $offset), '#');

        return '#^' . $regex . '$#';
    }
}
