<?php

declare(strict_types=1);

namespace Parcely\Domain;

final readonly class ParcelSearchResult implements \JsonSerializable
{
    /**
     * @param array{float, float, float, float} $bbox minLon, minLat, maxLon, maxLat (WGS 84)
     */
    public function __construct(
        public int $id,
        public string $label,
        public CodeValue $cadastralArea,
        public array $bbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'ku' => $this->cadastralArea, 'bbox' => $this->bbox];
    }
}
