<?php

declare(strict_types=1);

namespace Parcely\Repository;

use Parcely\Database\Database;
use Parcely\Database\Row;
use Parcely\Domain\CadastralArea;
use Parcely\Domain\CodeValue;

/**
 * District, municipality and cadastral area boundaries.
 */
final readonly class BoundaryRepository
{
    /**
     * ST_CoverageSimplify tolerance in EPSG:3857 metres. Measured on 20260930: the boundaries deviate at most
     * 18 m in EPSG:3857 (≈ 12 m on the ground at 50° N) and the GeoJSON has 2.2 MB, 0.6 MB gzipped.
     */
    public const int SIMPLIFY_TOLERANCE = 10;

    public function __construct(private Database $db) {}

    /**
     * One GeoJSON FeatureCollection in WGS 84 with properties type (okres | obec | ku), kod and nazev.
     *
     * The cadastral areas are simplified together as a coverage, so neighbours keep one shared border (no gaps,
     * no double lines); municipalities and the district are unions of them, so their borders lie exactly on
     * the cadastral borders. KÚ and obce form a valid coverage (ST_CoverageInvalidEdges = 0 on 20260930).
     */
    public function featureCollection(): string
    {
        $geojson = $this->db->fetchValue(
            <<<'SQL'
                WITH ku_simple AS (
                    SELECT kod, nazev, obec_kod, ST_SetSRID(ST_CoverageSimplify(geom, :tolerance) OVER (), 3857) AS geom
                    FROM ku
                ),
                obec_simple AS (
                    SELECT o.kod, o.nazev, ST_Union(k.geom) AS geom
                    FROM obec o
                    JOIN ku_simple k ON k.obec_kod = o.kod
                    GROUP BY o.kod, o.nazev
                ),
                features AS (
                    SELECT 1 AS rank, 'okres' AS type, kod, nazev, (SELECT ST_Union(geom) FROM obec_simple) AS geom FROM okres
                    UNION ALL
                    SELECT 2, 'obec', kod, nazev, geom FROM obec_simple
                    UNION ALL
                    SELECT 3, 'ku', kod, nazev, geom FROM ku_simple
                )
                SELECT json_build_object(
                    'type', 'FeatureCollection',
                    'features', json_agg(json_build_object(
                        'type', 'Feature',
                        'properties', json_build_object('type', type, 'kod', kod, 'nazev', nazev),
                        'geometry', ST_AsGeoJSON(ST_Transform(geom, 4326), 6)::json
                    ) ORDER BY rank, kod)
                )::text
                FROM features
                SQL,
            ['tolerance' => self::SIMPLIFY_TOLERANCE],
        );

        return is_string($geojson) ? $geojson : throw new \UnexpectedValueException('Boundaries query returned no GeoJSON');
    }

    /**
     * Cadastral areas whose name contains $query (case-insensitive), names starting with it first,
     * then in Czech alphabetical order (ch after h).
     *
     * @return list<CadastralArea>
     */
    public function suggestCadastralAreas(string $query, int $limit): array
    {
        // Backslash is the default LIKE escape character; the user's % and _ are literal characters.
        $escaped = addcslashes($query, '%_\\');
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT k.kod, k.nazev, o.kod AS obec_kod, o.nazev AS obec_nazev
                FROM ku k
                JOIN obec o ON o.kod = k.obec_kod
                WHERE k.nazev ILIKE :contains
                ORDER BY k.nazev ILIKE :prefix DESC, k.nazev COLLATE "cs-CZ-x-icu"
                LIMIT :limit
                SQL,
            ['contains' => '%' . $escaped . '%', 'prefix' => $escaped . '%', 'limit' => $limit],
        );

        return array_map(static fn(Row $row): CadastralArea => new CadastralArea(
            new CodeValue($row->int('kod'), $row->string('nazev')),
            new CodeValue($row->int('obec_kod'), $row->string('obec_nazev')),
        ), $rows);
    }
}
