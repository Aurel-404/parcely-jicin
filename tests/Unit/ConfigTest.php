<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit;

use Parcely\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const array ENV = [
        'DB_HOST' => 'db',
        'DB_NAME' => 'parcely',
        'DB_USER' => 'parcely',
        'DB_PASSWORD' => 'secret',
    ];

    public function testReadsEnvironmentWithDefaults(): void
    {
        $config = Config::fromEnvironment(self::ENV);

        self::assertSame('db', $config->dbHost);
        self::assertSame(5432, $config->dbPort);
        self::assertSame('prod', $config->appEnv);
        self::assertFalse($config->isDebug());
    }

    public function testDevEnvironmentEnablesDebug(): void
    {
        self::assertTrue(Config::fromEnvironment(['APP_ENV' => 'dev'] + self::ENV)->isDebug());
    }

    public function testMissingRequiredVariableFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB_PASSWORD');

        Config::fromEnvironment(['DB_PASSWORD' => ''] + self::ENV);
    }
}
