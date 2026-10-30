<?php

declare(strict_types=1);

namespace Parcely\Domain;

/**
 * Katastrální území with its municipality, for suggestions in the search box.
 */
final readonly class CadastralArea implements \JsonSerializable
{
    public function __construct(
        public CodeValue $area,
        public CodeValue $municipality,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['kod' => $this->area->code, 'nazev' => $this->area->name, 'obec' => $this->municipality];
    }
}
