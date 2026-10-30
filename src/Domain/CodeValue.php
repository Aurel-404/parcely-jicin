<?php

declare(strict_types=1);

namespace Parcely\Domain;

/**
 * A code with its name: a code list entry (druh pozemku …) or an area (KÚ, obec).
 */
final readonly class CodeValue implements \JsonSerializable
{
    public function __construct(
        public int $code,
        public string $name,
    ) {}

    /**
     * @return array{kod: int, nazev: string}
     */
    public function jsonSerialize(): array
    {
        return ['kod' => $this->code, 'nazev' => $this->name];
    }
}
