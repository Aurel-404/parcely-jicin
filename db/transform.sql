-- Builds the application tables from the raw VFR layers in schema staging (loaded by bin/import.sh).
-- bin/import.sh runs it in one transaction (psql -1 -v vfr_date=YYYYMMDD): nobody sees half-imported data and a
-- failed import leaves the previous data untouched. DROP TABLE locks these tables and the referenced code lists
-- until COMMIT, so API requests and uncached tiles wait for the transform (~13 s); cached tiles are still served.
--
-- VFR geometries are in S-JTSK / Krovak East North (EPSG:5514). The tables store Web Mercator (EPSG:3857),
-- the projection of vector tiles, so a tile request does not reproject anything.
--
-- Codes reference the code lists cis_* (bin/import-codelists.php): a code missing from ST_UCIS fails the import.

DO $$
BEGIN
    -- Parcel labels get the prefix "st." for building plots: code 1 = "Stavební" in DruhyCislovaniParcel (ST_UCIS).
    IF NOT EXISTS (SELECT FROM cis_druh_cislovani WHERE kod = 1 AND nazev = 'Stavební') THEN
        RAISE EXCEPTION 'cis_druh_cislovani has no code 1 = Stavební; check ST_UCIS and bin/import-codelists.php';
    END IF;
    -- The parcel geometry is OriginalniHranice. A parcel without it is not skipped silently.
    IF EXISTS (SELECT FROM staging.parcely WHERE originalnihranice IS NULL) THEN
        RAISE EXCEPTION '% parcels have no OriginalniHranice (% of them have OriginalniHraniceOmpv)',
            (SELECT count(*) FROM staging.parcely WHERE originalnihranice IS NULL),
            (SELECT count(*) FROM staging.parcely WHERE originalnihranice IS NULL AND originalnihraniceompv IS NOT NULL);
    END IF;
END $$;

-- Valid MultiPolygon in EPSG:3857. ST_MakeValid can return a collection with stray lines; keep the polygons.
CREATE FUNCTION pg_temp.web_multipolygon(g geometry) RETURNS geometry
    LANGUAGE sql IMMUTABLE STRICT
    RETURN ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Transform(g, 3857)), 3));

DROP TABLE IF EXISTS building, parcel, ku, obec, okres CASCADE;

CREATE TABLE okres (
    kod   int PRIMARY KEY,
    nazev text NOT NULL,
    geom  geometry(MultiPolygon, 3857) NOT NULL
);
-- ST_UKSH lists all districts; keep the one the imported municipalities belong to.
INSERT INTO okres (kod, nazev, geom)
SELECT kod, nazev, pg_temp.web_multipolygon(originalnihranice)
FROM staging.okresy
WHERE kod IN (SELECT okreskod FROM staging.obce);

CREATE TABLE obec (
    kod   int PRIMARY KEY,
    nazev text NOT NULL,
    geom  geometry(MultiPolygon, 3857)
);
INSERT INTO obec (kod, nazev, geom)
SELECT kod, nazev, pg_temp.web_multipolygon(originalnihranice)
FROM staging.obce;

CREATE TABLE ku (
    kod      int PRIMARY KEY,
    nazev    text NOT NULL,
    obec_kod int NOT NULL REFERENCES obec (kod),
    geom     geometry(MultiPolygon, 3857)
);
INSERT INTO ku (kod, nazev, obec_kod, geom)
SELECT kod, nazev, obeckod, pg_temp.web_multipolygon(originalnihranice)
FROM staging.katastralniuzemi;

CREATE TABLE parcel (
    id                 bigint PRIMARY KEY,  -- parcel ID in RÚIAN = id in Nahlížení do KN
    ku_kod             int NOT NULL REFERENCES ku (kod),
    kmenove_cislo      int NOT NULL,
    poddeleni          int,
    druh_cislovani_kod smallint REFERENCES cis_druh_cislovani (kod),
    druh_pozemku_kod   smallint REFERENCES cis_druh_pozemku (kod),
    zpusob_vyuziti_kod smallint REFERENCES cis_zpusob_vyuziti (kod),
    vymera_m2          int,
    label              text NOT NULL,       -- "st. 123/4" or "567/2"
    geom               geometry(MultiPolygon, 3857) NOT NULL
);
INSERT INTO parcel (id, ku_kod, kmenove_cislo, poddeleni, druh_cislovani_kod, druh_pozemku_kod, zpusob_vyuziti_kod,
                    vymera_m2, label, geom)
SELECT id, katastralniuzemikod, kmenovecislo, pododdelenicisla, druhcislovanikod, druhpozemkukod, zpusobyvyuzitipozemku,
       vymeraparcely,
       CASE WHEN druhcislovanikod = 1 THEN 'st. ' ELSE '' END || kmenovecislo || COALESCE('/' || pododdelenicisla, ''),
       pg_temp.web_multipolygon(originalnihranice)
FROM staging.parcely;

-- Buildings, for "Na pozemku stojí stavba č. p. …" in the parcel detail. Only buildings whose identification
-- parcel is imported: a few RÚIAN buildings have none, others stand on a parcel outside the district.
CREATE TABLE building (
    id                 bigint PRIMARY KEY,  -- kód stavebního objektu in RÚIAN
    parcel_id          bigint NOT NULL REFERENCES parcel (id),  -- identifikační parcela
    cisla_domovni      int[],               -- č. p. or č. ev. depending on typ_kod; a building can have several
    typ_kod            smallint REFERENCES cis_typ_stavebniho_objektu (kod),
    zpusob_vyuziti_kod smallint REFERENCES cis_zpusob_vyuziti_objektu (kod)
);
INSERT INTO building (id, parcel_id, cisla_domovni, typ_kod, zpusob_vyuziti_kod)
SELECT kod, identifikacniparcelaid, cislodomovni, typstavebnihoobjektukod, zpusobvyuzitikod
FROM staging.stavebniobjekty
WHERE identifikacniparcelaid IN (SELECT id FROM parcel);

CREATE INDEX parcel_geom_gix ON parcel USING GIST (geom);
CREATE INDEX parcel_ku_cislo_idx ON parcel (ku_kod, kmenove_cislo, poddeleni);
CREATE INDEX ku_geom_gix ON ku USING GIST (geom);
CREATE INDEX building_parcel_idx ON building (parcel_id);

CREATE TABLE IF NOT EXISTS meta (
    key   text PRIMARY KEY,
    value text NOT NULL
);
-- vfr_date versions the tile cache (var/tiles/{vfr_date}/…), so the import does not have to delete it.
INSERT INTO meta (key, value)
VALUES ('vfr_date', :'vfr_date'),
       ('imported_at', to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS"Z"'))
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value;

ANALYZE okres, obec, ku, parcel, building;
