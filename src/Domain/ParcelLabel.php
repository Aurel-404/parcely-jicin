<?php

declare(strict_types=1);

namespace Parcely\Domain;

/**
 * Parcel number as written in the cadastre: "st. 123/4" (building plot), "567/2", "567".
 */
final readonly class ParcelLabel implements \Stringable
{
    /**
     * DruhyCislovaniParcel in ST_UCIS: 1 = Stavební (the "st." prefix), 2 = Pozemková.
     * db/transform.sql checks code 1 on every import.
     */
    public const int BUILDING_PLOT = 1;

    public function __construct(
        public bool $buildingPlot,
        public int $number,
        public ?int $subdivision = null,
    ) {
        if ($number < 1 || ($subdivision !== null && $subdivision < 1)) {
            throw new \InvalidArgumentException('Parcel number and subdivision must be positive');
        }
    }

    /**
     * Accepts what people type: "st. 123/4", "St.123", "st 123 / 4", "123/4", "123".
     *
     * @throws \InvalidArgumentException
     */
    public static function parse(string $text): self
    {
        if (preg_match('~^\s*(st\.?\s*)?([0-9]{1,9})\s*(?:/\s*([0-9]{1,9}))?\s*$~i', $text, $matches) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a parcel number like "123", "123/4" or "st. 123/4"', $text));
        }

        return new self(
            buildingPlot: $matches[1] !== '',
            number: (int) $matches[2],
            subdivision: isset($matches[3]) ? (int) $matches[3] : null,
        );
    }

    /** The same format as parcel.label (db/transform.sql). */
    public function format(): string
    {
        return ($this->buildingPlot ? 'st. ' : '') . $this->number . ($this->subdivision !== null ? '/' . $this->subdivision : '');
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
