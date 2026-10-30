<?php

declare(strict_types=1);

namespace Parcely\Vfr;

/**
 * Municipality (obec) as listed in RÚIAN.
 */
final readonly class Municipality
{
    public function __construct(
        public int $code,
        public string $name,
    ) {}
}
