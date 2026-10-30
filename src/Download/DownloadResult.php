<?php

declare(strict_types=1);

namespace Parcely\Download;

final readonly class DownloadResult
{
    public function __construct(
        public string $path,
        public int $bytes,
        public bool $skipped,
    ) {}
}
