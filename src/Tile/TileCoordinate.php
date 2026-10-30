<?php

declare(strict_types=1);

namespace Parcely\Tile;

/**
 * A tile of the Web Mercator tile pyramid (XYZ scheme, y grows southwards), validated.
 */
final readonly class TileCoordinate
{
    /** Deepest zoom served; the map overzooms beyond it (parcels are still sharp at 4096 units per tile). */
    public const int MAX_ZOOM = 16;

    /** Half the width of the Web Mercator world in metres; the same bounds as PostGIS ST_TileEnvelope. */
    private const float WORLD_HALF = 20037508.342789244;

    private function __construct(
        public int $z,
        public int $x,
        public int $y,
    ) {}

    /**
     * @throws \InvalidArgumentException for anything but 0 ≤ z ≤ MAX_ZOOM and 0 ≤ x, y < 2^z
     */
    public static function of(int $z, int $x, int $y): self
    {
        if ($z < 0 || $z > self::MAX_ZOOM) {
            throw new \InvalidArgumentException(sprintf('Zoom must be between 0 and %d', self::MAX_ZOOM));
        }
        $count = 1 << $z;
        if ($x < 0 || $x >= $count || $y < 0 || $y >= $count) {
            throw new \InvalidArgumentException(sprintf('Tile x and y must be between 0 and %d at zoom %d', $count - 1, $z));
        }

        return new self($z, $x, $y);
    }

    /**
     * From URL path segments, e.g. "14", "8892", "5526".
     *
     * @throws \InvalidArgumentException
     */
    public static function fromStrings(string $z, string $x, string $y): self
    {
        foreach ([$z, $x, $y] as $value) {
            if (preg_match('/^\d{1,6}$/', $value) !== 1) {
                throw new \InvalidArgumentException('Tile coordinates must be non-negative integers');
            }
        }

        return self::of((int) $z, (int) $x, (int) $y);
    }

    /** Tile width in metres. */
    public function size(): float
    {
        return 2 * self::WORLD_HALF / (1 << $this->z);
    }

    public function envelope(): Envelope
    {
        $size = $this->size();
        $minX = -self::WORLD_HALF + $this->x * $size;
        $maxY = self::WORLD_HALF - $this->y * $size;

        return new Envelope($minX, $maxY - $size, $minX + $size, $maxY);
    }

    public function path(): string
    {
        return sprintf('%d/%d/%d', $this->z, $this->x, $this->y);
    }
}
