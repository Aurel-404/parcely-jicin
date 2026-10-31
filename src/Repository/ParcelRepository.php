<?php

declare(strict_types=1);

namespace Parcely\Repository;

use Parcely\Database\Database;
use Parcely\Database\Row;
use Parcely\Domain\Building;
use Parcely\Domain\CodeValue;
use Parcely\Domain\LandTypeCount;
use Parcely\Domain\ParcelDetail;
use Parcely\Domain\ParcelLabel;
use Parcely\Domain\ParcelSearchResult;

final readonly class ParcelRepository
{
    /** Bounding box of p.geom in WGS 84; a Web Mercator rectangle stays a rectangle in WGS 84. */
    private const string BBOX = <<<'SQL'
        CROSS JOIN LATERAL (SELECT ST_Transform(ST_Envelope(p.geom), 4326) AS geom) bbox
        SQL;
    private const string BBOX_COLUMNS = <<<'SQL'
        ST_XMin(bbox.geom) AS min_lon, ST_YMin(bbox.geom) AS min_lat, ST_XMax(bbox.geom) AS max_lon, ST_YMax(bbox.geom) AS max_lat
        SQL;

    public function __construct(private Database $db) {}

    public function find(int $id): ?ParcelDetail
    {
        // ST_PointOnSurface instead of ST_Centroid, because the centroid of an L-shaped parcel can lie outside it.
        $row = $this->db->fetchRow(
            'SELECT p.id, p.label, p.vymera_m2,
                    k.kod AS ku_kod, k.nazev AS ku_nazev, o.kod AS obec_kod, o.nazev AS obec_nazev,
                    p.druh_pozemku_kod, dp.nazev AS druh_pozemku_nazev,
                    p.zpusob_vyuziti_kod, zv.nazev AS zpusob_vyuziti_nazev,
                    ST_X(point.geom) AS lon, ST_Y(point.geom) AS lat, ' . self::BBOX_COLUMNS . ",
                    (SELECT value FROM meta WHERE key = 'vfr_date') AS vfr_date
             FROM parcel p
             JOIN ku k ON k.kod = p.ku_kod
             JOIN obec o ON o.kod = k.obec_kod
             LEFT JOIN cis_druh_pozemku dp ON dp.kod = p.druh_pozemku_kod
             LEFT JOIN cis_zpusob_vyuziti zv ON zv.kod = p.zpusob_vyuziti_kod
             CROSS JOIN LATERAL (SELECT ST_Transform(ST_PointOnSurface(p.geom), 4326) AS geom) point
             " . self::BBOX . '
             WHERE p.id = :id',
            ['id' => $id],
        );
        if ($row === null) {
            return null;
        }

        return new ParcelDetail(
            id: $row->int('id'),
            label: $row->string('label'),
            cadastralArea: new CodeValue($row->int('ku_kod'), $row->string('ku_nazev')),
            municipality: new CodeValue($row->int('obec_kod'), $row->string('obec_nazev')),
            areaM2: $row->int('vymera_m2'),
            landType: self::codeValue($row, 'druh_pozemku'),
            landUse: self::codeValue($row, 'zpusob_vyuziti'),
            buildings: $this->buildings($id),
            point: [round($row->float('lon'), 6), round($row->float('lat'), 6)],
            bbox: self::bbox($row),
            vfrDate: $row->string('vfr_date'),
        );
    }

    /**
     * Parcels by cadastral area (name or code) and number. Without a subdivision ("123") it finds 123 and
     * 123/1, 123/2 …; without "st." it finds both kinds, land plots (pozemková) first.
     *
     * @return list<ParcelSearchResult>
     */
    public function search(string $cadastralArea, ParcelLabel $label, int $limit): array
    {
        // Only fixed fragments are added; every value is a parameter.
        $conditions = ['(k.kod::text = :ku_code OR lower(k.nazev) = lower(:ku_name))', 'p.kmenove_cislo = :number'];
        $params = ['ku_code' => $cadastralArea, 'ku_name' => $cadastralArea, 'number' => $label->number, 'limit' => $limit];
        if ($label->subdivision !== null) {
            $conditions[] = 'p.poddeleni = :subdivision';
            $params['subdivision'] = $label->subdivision;
        }
        if ($label->buildingPlot) {
            $conditions[] = 'p.druh_cislovani_kod = :building_plot';
            $params['building_plot'] = ParcelLabel::BUILDING_PLOT;
        }

        // druh_cislovani_kod DESC: 2 = Pozemková before 1 = Stavební.
        $rows = $this->db->fetchAll(
            'SELECT p.id, p.label, k.kod AS ku_kod, k.nazev AS ku_nazev, ' . self::BBOX_COLUMNS . '
             FROM parcel p
             JOIN ku k ON k.kod = p.ku_kod
             ' . self::BBOX . '
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY p.druh_cislovani_kod DESC, p.poddeleni NULLS FIRST, p.id
             LIMIT :limit',
            $params,
        );

        return array_map(static fn(Row $row): ParcelSearchResult => new ParcelSearchResult(
            id: $row->int('id'),
            label: $row->string('label'),
            cadastralArea: new CodeValue($row->int('ku_kod'), $row->string('ku_nazev')),
            bbox: self::bbox($row),
        ), $rows);
    }

    /**
     * Kinds of land that occur in the district with their parcel counts, most frequent first.
     *
     * @return list<LandTypeCount>
     */
    public function countByLandType(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT d.kod, d.nazev, count(*) AS pocet
             FROM parcel p
             JOIN cis_druh_pozemku d ON d.kod = p.druh_pozemku_kod
             GROUP BY d.kod, d.nazev
             ORDER BY pocet DESC, d.kod',
        );

        return array_map(static fn(Row $row): LandTypeCount => new LandTypeCount(
            new CodeValue($row->int('kod'), $row->string('nazev')),
            $row->int('pocet'),
        ), $rows);
    }

    /**
     * @return list<Building>
     */
    private function buildings(int $parcelId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT b.id, b.typ_kod, t.nazev AS typ_nazev, b.zpusob_vyuziti_kod, z.nazev AS zpusob_vyuziti_nazev,
                    COALESCE(array_to_json(b.cisla_domovni), '[]') AS cisla_domovni
             FROM building b
             LEFT JOIN cis_typ_stavebniho_objektu t ON t.kod = b.typ_kod
             LEFT JOIN cis_zpusob_vyuziti_objektu z ON z.kod = b.zpusob_vyuziti_kod
             WHERE b.parcel_id = :parcel_id
             ORDER BY b.id",
            ['parcel_id' => $parcelId],
        );

        return array_map(static fn(Row $row): Building => new Building(
            id: $row->int('id'),
            type: self::codeValue($row, 'typ'),
            houseNumbers: $row->intList('cisla_domovni'),
            use: self::codeValue($row, 'zpusob_vyuziti'),
        ), $rows);
    }

    /**
     * Columns {prefix}_kod and {prefix}_nazev; null when the code is empty.
     */
    private static function codeValue(Row $row, string $prefix): ?CodeValue
    {
        $code = $row->nullableInt($prefix . '_kod');

        return $code === null ? null : new CodeValue($code, $row->string($prefix . '_nazev'));
    }

    /**
     * @return array{float, float, float, float}
     */
    private static function bbox(Row $row): array
    {
        return [
            round($row->float('min_lon'), 6),
            round($row->float('min_lat'), 6),
            round($row->float('max_lon'), 6),
            round($row->float('max_lat'), 6),
        ];
    }
}
