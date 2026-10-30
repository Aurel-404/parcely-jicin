<?php

declare(strict_types=1);

namespace Parcely\Tile;

final readonly class CachedFile
{
    /**
     * @param bool $hit true = served from the cache, false = rendered just now
     */
    public function __construct(
        public string $path,
        public bool $hit,
    ) {}
}
