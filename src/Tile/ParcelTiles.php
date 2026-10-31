<?php

declare(strict_types=1);

namespace Parcely\Tile;

/**
 * The parcel tile layer: answers empty tiles without the database, everything else from the disk cache,
 * rendering on a miss.
 */
final readonly class ParcelTiles
{
    /** PARCEL_MIN_ZOOM: below it a tile would hold too many parcels; tuned by the benchmark (bin/bench.php). */
    public const int MIN_ZOOM = 13;

    /**
     * Extent of the district in EPSG:3857: ST_Extent(okres.geom) of data set 20260930, rounded outwards to
     * whole metres. ApiTest checks that it still covers the imported district.
     */
    public const array DISTRICT_EXTENT = [1681395.0, 6494089.0, 1752343.0, 6541545.0];

    private Envelope $district;

    public function __construct(
        private TileRenderer $renderer,
        private TileCache $cache,
    ) {
        $this->district = self::districtExtent();
    }

    public static function districtExtent(): Envelope
    {
        return new Envelope(...self::DISTRICT_EXTENT);
    }

    /**
     * False = the tile is empty for sure: below MIN_ZOOM or outside the district. Parcels just outside a tile
     * still reach into its buffer, so the tile is widened by the buffer before the test.
     */
    public function mayContainParcels(TileCoordinate $tile): bool
    {
        if ($tile->z < self::MIN_ZOOM) {
            return false;
        }
        $buffer = $tile->size() * TileRenderer::BUFFER / TileRenderer::EXTENT;

        return $tile->envelope()->expand($buffer)->intersects($this->district);
    }

    /**
     * TileJSON 3.0 of the layer. The map takes the zoom range and bounds from here, so MIN_ZOOM lives only in
     * this class. The data set date in the tile URL makes browsers load new tiles right after an import
     * instead of keeping cached ones for up to a day.
     *
     * @return array<string, mixed>
     */
    public function tileJson(string $vfrDate): array
    {
        return [
            'tilejson' => '3.0.0',
            'name' => 'parcels',
            'tiles' => ['/tiles/parcels/{z}/{x}/{y}.pbf?v=' . $vfrDate],
            'minzoom' => self::MIN_ZOOM,
            'maxzoom' => TileCoordinate::MAX_ZOOM,
            'bounds' => $this->district->toLonLat(),
            'vector_layers' => [['id' => 'parcels', 'fields' => ['label' => 'String', 'druh_pozemku_kod' => 'Number']]],
            'attribution' => 'Parcely: RÚIAN © ČÚZK (CC BY 4.0)',
        ];
    }

    public function get(TileCoordinate $tile, string $vfrDate): CachedFile
    {
        return $this->cache->remember($vfrDate, 'parcels/' . $tile->path() . '.pbf', fn(): string => $this->renderer->parcels($tile));
    }
}
