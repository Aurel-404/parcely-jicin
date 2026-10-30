<?php

declare(strict_types=1);

namespace Parcely\Domain;

/**
 * A building whose identification parcel is the parcel shown ("Na pozemku stojí stavba …").
 */
final readonly class Building implements \JsonSerializable
{
    /** TypyStavebnichObjektu in ST_UCIS: 1 = budova s číslem popisným, 2 = budova s číslem evidenčním. */
    private const array NUMBER_PREFIX = [1 => 'č. p.', 2 => 'č. ev.'];

    /**
     * @param list<int> $houseNumbers several for a building with more entrances
     */
    public function __construct(
        public int $id,
        public ?CodeValue $type,
        public array $houseNumbers,
        public ?CodeValue $use,
    ) {}

    /** "č. p. 540", "č. ev. 12", "č. p. 3, 5"; null for a building without a number. */
    public function designation(): ?string
    {
        $prefix = self::NUMBER_PREFIX[$this->type->code ?? 0] ?? null;
        if ($prefix === null || $this->houseNumbers === []) {
            return null;
        }

        return $prefix . ' ' . implode(', ', $this->houseNumbers);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'oznaceni' => $this->designation(),
            'typ' => $this->type,
            'cisla_domovni' => $this->houseNumbers,
            'zpusob_vyuziti' => $this->use,
        ];
    }
}
