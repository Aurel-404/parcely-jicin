<?php

declare(strict_types=1);

namespace Parcely\Domain;

/**
 * A druh pozemku (name from ST_UCIS) with the number of parcels of that kind, for the map legend.
 */
final readonly class LandTypeCount implements \JsonSerializable
{
    public function __construct(
        public CodeValue $type,
        public int $parcels,
    ) {}

    /**
     * @return array{kod: int, nazev: string, pocet: int}
     */
    public function jsonSerialize(): array
    {
        return ['kod' => $this->type->code, 'nazev' => $this->type->name, 'pocet' => $this->parcels];
    }
}
