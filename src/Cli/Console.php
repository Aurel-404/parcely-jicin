<?php

declare(strict_types=1);

namespace Parcely\Cli;

final readonly class Console
{
    /**
     * @param resource $out
     * @param resource $err
     */
    public function __construct(
        private mixed $out = STDOUT,
        private mixed $err = STDERR,
    ) {}

    public function info(string $message): void
    {
        fwrite($this->out, $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite($this->err, $message . PHP_EOL);
    }

    public static function bytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_000_000 => sprintf('%.1f MB', $bytes / 1_000_000),
            $bytes >= 1_000 => sprintf('%.1f kB', $bytes / 1_000),
            default => sprintf('%d B', $bytes),
        };
    }

    public static function seconds(int $startedNs): string
    {
        return sprintf('%.1f s', (hrtime(true) - $startedNs) / 1e9);
    }
}
