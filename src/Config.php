<?php

declare(strict_types=1);

namespace Parcely;

use Parcely\Vfr\VfrSource;

/**
 * Application configuration read from environment variables (set by Docker Compose from .env).
 */
final readonly class Config
{
    public function __construct(
        public string $appEnv,
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPassword,
        public string $vfrBaseUrl = VfrSource::DEFAULT_BASE_URL,
    ) {}

    /**
     * @param array<string, string> $env typically getenv()
     */
    public static function fromEnvironment(array $env): self
    {
        return new self(
            appEnv: $env['APP_ENV'] ?? 'prod',
            dbHost: self::required($env, 'DB_HOST'),
            dbPort: (int) ($env['DB_PORT'] ?? 5432),
            dbName: self::required($env, 'DB_NAME'),
            dbUser: self::required($env, 'DB_USER'),
            dbPassword: self::required($env, 'DB_PASSWORD'),
            vfrBaseUrl: ($env['VFR_BASE_URL'] ?? '') !== '' ? $env['VFR_BASE_URL'] : VfrSource::DEFAULT_BASE_URL,
        );
    }

    public function isDebug(): bool
    {
        return $this->appEnv === 'dev';
    }

    /**
     * @param array<string, string> $env
     */
    private static function required(array $env, string $name): string
    {
        $value = $env[$name] ?? '';
        if ($value === '') {
            throw new \RuntimeException(sprintf('Missing required environment variable %s (see .env.example).', $name));
        }

        return $value;
    }
}
