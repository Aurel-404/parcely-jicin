<?php

declare(strict_types=1);

namespace Parcely\Tile;

use Parcely\Database\Database;

/**
 * Renders Mapbox Vector Tiles in PostGIS. Besides the repositories, this is the only class with SQL.
 */
final readonly class TileRenderer
{
    /** Tile coordinate space (MVT default). */
    public const int EXTENT = 4096;
    /** Geometry kept outside the tile edge, in tile units, so polygon outlines do not end at the edge. */
    public const int BUFFER = 64;

    public function __construct(private Database $db) {}

    /**
     * Layer "parcels" with the minimum the map needs: feature id = parcel id (for feature-state hover and
     * selection), druh_pozemku_kod and, at the deepest zoom only, label. Everything else is loaded on click
     * (/api/parcels/{id}).
     *
     * The map shows parcel numbers from zoom 17, which it draws from the z16 tiles (overzoom), so a label in a
     * tile below z16 would never be used (ST_AsMVT leaves NULL properties out).
     *
     * @return string MVT bytes, empty when no parcel intersects the tile
     */
    public function parcels(TileCoordinate $tile): string
    {
        $mvt = $this->db->fetchValue(
            <<<'SQL'
                WITH bounds AS (SELECT ST_TileEnvelope(:z, :x, :y) AS geom),
                mvt AS (
                    SELECT p.id, CASE WHEN :with_label THEN p.label END AS label, p.druh_pozemku_kod,
                           ST_AsMVTGeom(p.geom, b.geom, :extent, :buffer, true) AS geom
                    FROM parcel p, bounds b
                    WHERE p.geom && b.geom
                )
                SELECT ST_AsMVT(mvt.*, 'parcels', :layer_extent, 'geom', 'id') FROM mvt
                SQL,
            // PDO cannot use one named parameter twice, hence extent and layer_extent.
            [
                'z' => $tile->z, 'x' => $tile->x, 'y' => $tile->y,
                'with_label' => $tile->z === TileCoordinate::MAX_ZOOM,
                'extent' => self::EXTENT, 'buffer' => self::BUFFER, 'layer_extent' => self::EXTENT,
            ],
        );

        // pdo_pgsql returns bytea as a stream.
        $bytes = is_resource($mvt) ? stream_get_contents($mvt) : $mvt;
        if (!is_string($bytes)) {
            throw new \UnexpectedValueException('ST_AsMVT did not return bytes');
        }

        return $bytes;
    }
}
