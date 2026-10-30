<?php

declare(strict_types=1);

namespace Parcely\Tile;

/**
 * Axis-aligned rectangle in Web Mercator (EPSG:3857) metres.
 */
final readonly class Envelope
{
    public function __construct(
        public float $minX,
        public float $minY,
        public float $maxX,
        public float $maxY,
    ) {
        if ($minX > $maxX || $minY > $maxY) {
            throw new \InvalidArgumentException('Envelope minimum is greater than maximum');
        }
    }

    public function intersects(self $other): bool
    {
        return $this->minX <= $other->maxX && $other->minX <= $this->maxX
            && $this->minY <= $other->maxY && $other->minY <= $this->maxY;
    }

    public function contains(self $other): bool
    {
        return $this->minX <= $other->minX && $other->maxX <= $this->maxX
            && $this->minY <= $other->minY && $other->maxY <= $this->maxY;
    }

    public function expand(float $margin): self
    {
        return new self($this->minX - $margin, $this->minY - $margin, $this->maxX + $margin, $this->maxY + $margin);
    }
}
