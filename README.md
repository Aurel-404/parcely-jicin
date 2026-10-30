# Parcely okresu Jičín

Webová aplikácia, ktorá na mape zobrazí všetky parcely okresu Jičín. Po kliknutí na parcelu
ukáže informácie o nej. Dáta pochádzajú z RÚIAN (ČÚZK).

> Projekt vzniká po fázach. Toto README sa dopĺňa priebežne, finálna verzia vznikne vo fáze 07.

## Predpoklady

- Docker s Docker Compose v2 (Docker Desktop na Windows/macOS alebo Docker Engine na Linuxe)
- Voľné porty `8080` (web) a `5433` (PostgreSQL), dajú sa zmeniť v `.env`

Lokálne PHP, PostgreSQL ani GDAL netreba, všetko beží v kontajneroch.

## Spustenie

```bash
cp .env.example .env
docker compose up -d --build
```

Pri prvom štarte sa automaticky spustí `composer install`. Hodnoty v `.env` netreba meniť, heslo
`change-me` je len zástupné a databáza je dostupná iba z vlastného počítača.

Aplikácia beží na <http://localhost:8080>, stav na <http://localhost:8080/api/health>.

## Stiahnutie dát

```bash
docker compose exec php php bin/download.php
```

Stiahne výmenný formát RÚIAN (VFR) z ČÚZK do `data/raw` (volume `raw-data`): súbor `OB_{kód}_UKSH`
pre každú zo 111 obcí okresu, hranice okresu (`ST_UKSH`) a číselníky (`ST_UCIS`). Predvolene berie
mesačnú sadu k poslednému dňu minulého mesiaca, iný dátum sa dá zadať cez `--date=RRRRMMDD`.
Opakované spustenie už stiahnuté súbory preskočí. Prerušené sťahovanie pokračuje od súboru, kde skončilo.

Zoznam obcí je v repe ([data/obce_okres_jicin.csv](data/obce_okres_jicin.csv)). Vznikol zo štátneho
súboru `ST_UZSZ` a dá sa vygenerovať znova:

```bash
docker compose exec php php bin/list-obce.php
```

Skript skončí s chybou, ak nevyjde 111 obcí (oficiálny počet podľa ČSÚ).

## Import do databázy

```bash
docker compose exec php php bin/import-codelists.php
docker compose run --rm gdal bin/import.sh
```

Prvý príkaz nahrá číselníky (druh pozemku, spôsob využitia, druh číslovania, typ stavby) zo súboru `ST_UCIS`
do tabuliek `cis_*`. Druhý nahrá VFR všetkých 111 obcí cez `ogr2ogr` do schémy `staging`. Skript
[db/transform.sql](db/transform.sql) z nich potom postaví tabuľky aplikácie `okres`, `obec`, `ku`, `parcel` a `building`
v EPSG:3857. Transformácia beží v jednej transakcii, takže aplikácia až do jej konca vidí predchádzajúce dáta.
Dátum sady sa zapíše do tabuľky `meta` (`vfr_date`).

- Importuje sa sada s najnovším dátumom v názvoch stiahnutých súborov, iný dátum sa dá zadať cez `--date=RRRRMMDD`.
  Ak pre ten dátum chýba hoci jeden súbor, import skončí s chybou.
- `--obec=572659` naimportuje len jednu obec (rýchla skúška, obec Jičín trvá 4 s).
- Celý import trvá asi 50 s.

Mesačná aktualizácia = `bin/download.php`, potom oba príkazy importu a nakoniec
`docker compose exec php php bin/clear-cache.php` (zmaže cache starých sád, pozri nižšie). Schéma `staging`
(surové vrstvy, 184 MB) po importe ostáva na kontrolu dát SQL dotazmi a ďalší import ju zahodí.

Výsledok pre sadu 20260930:

| Tabuľka | Počet |
|---|---|
| `obec` | 111 |
| `ku` (katastrálne územia) | 240 |
| `parcel` | 271 995 |
| `building` (stavby na parcelách okresu) | 45 307 |

Súčet výmer parciel (886,86 km²) sedí s plochou okresu (886,83 km²). Polia VFR a ich mapovanie na tabuľky sú
v [docs/vfr-schema.md](docs/vfr-schema.md).

## Mapa

<http://localhost:8080> (Chrome, Firefox; na telefóne spodný panel namiesto bočného).

- Pri štarte je v zábere celý okres s hranicami a názvami katastrálnych území. Parcely sa zobrazia od zoomu 13,
  ich čísla od zoomu 17.
- Klik na parcelu otvorí detail: číslo, KÚ, obec, výmera, druh pozemku, spôsob využitia, stavby na pozemku,
  odkazy do Nahlížení do KN (vlastníci) a do VDP.
- Odkaz na parcelu sa dá zdieľať: `http://localhost:8080/#parcel=1755256604`. Tlačidlo Späť funguje, `Esc`
  zavrie detail.
- Hľadanie: katastrálne územie (s našepkávaním) a číslo, napr. `Jičín` + `st. 1868/26`.
- Podklad: OpenStreetMap alebo ortofoto ČÚZK (voľba sa pamätá v prehliadači).

Frontend nemá build krok: [public/index.html](public/index.html), [public/assets/app.js](public/assets/app.js)
(ES modul), [public/assets/app.css](public/assets/app.css). Externé zdroje (všetky overené, vracajú 200 a CORS):

| Zdroj | Na čo |
|---|---|
| `unpkg.com/maplibre-gl@5.24.0` | MapLibre GL JS 5.24.0, pripnutá verzia s kontrolou integrity (SRI) |
| `tile.openstreetmap.org` | podklad OpenStreetMap; pre lokálny vývoj a ukážku, pri reálnej prevádzke by bol potrebný vlastný zdroj dlaždíc ([pravidlá](https://operations.osmfoundation.org/policies/tiles/)) |
| `ags.cuzk.gov.cz/…/ORTOFOTO_WM/MapServer/tile/{z}/{y}/{x}` | ortofoto ČÚZK (pozor: y pred x) |
| `demotiles.maplibre.org/font/…` | písmo pre popisky (Noto Sans, aj česká diakritika) |

## API

| Endpoint | Čo vráti |
|---|---|
| `GET /tiles/parcels/{z}/{x}/{y}.pbf` | vektorová dlaždica (MVT), vrstva `parcels`: id parcely, `label`, `druh_pozemku_kod`; zoom 13–16 |
| `GET /api/parcels/{id}` | detail parcely: KÚ, obec, výmera, druh pozemku, spôsob využitia, stavby, bod a bbox (WGS 84), odkazy do Nahlížení do KN a VDP |
| `GET /api/search?ku=Jičín&cislo=st. 1868/26` | parcely podľa KÚ (názov alebo kód) a čísla, najviac 10 |
| `GET /api/ku?q=jič` | KÚ, ktorých názov obsahuje text (našepkávanie), najviac 10 |
| `GET /tiles/parcels.json` | TileJSON vrstvy parciel: URL dlaždíc s dátumom sady (`?v=20260930`), rozsah zoomov, hranice okresu |
| `GET /api/boundaries.geojson` | hranice okresu, obcí a KÚ (zjednodušené, WGS 84) a bod pre popisok každého KÚ |
| `GET /api/health` | stav aplikácie a databázy |

```bash
curl http://localhost:8080/api/parcels/1755256604
curl "http://localhost:8080/api/search?ku=Ji%C4%8D%C3%ADn&cislo=st.%201868/26"
curl -I http://localhost:8080/tiles/parcels/14/8892/5526.pbf
```

- Bez `st.` hľadanie nájde pozemkové aj stavebné parcely (pozemkové prvé), bez lomky aj všetky poddelenia
  (`123` → `123`, `123/1`, …).
- Pod zoomom 13 a mimo okresu je dlaždica prázdna (200, prázdne telo) a odpovie sa bez databázy.
- Chyby sú JSON `{"error": "…"}` s kódom 400 (neplatný vstup), 404, 405 alebo 503 (dáta ešte nie sú
  naimportované). Neočakávané chyby sa zapisujú do `var/log/app.log` (volume `app-var`).

**Cache.** Dlaždice a hranice sa po prvom vykreslení uložia do `var/tiles/{vfr_date}/…`. Nová sada má nový
`vfr_date`, a teda prázdny priečinok, takže sa nikdy nepoužije stará dlaždica a import nemusí nič mazať.
Prehliadač dostane `Cache-Control` a `ETag` (pri zhode odpoveď 304). Namerané cez nginx: vykreslenie dlaždice
z14 p50 45 ms, z cache 7 ms.

**Vlastníci.** V aplikácii nie sú: VFR (otvorené dáta RÚIAN) údaje o vlastníkoch ani o listoch vlastníctva
neobsahuje (pozri [docs/vfr-schema.md](docs/vfr-schema.md)). Vedie ich katastr nemovitostí a zobrazuje ich
Nahlížení do KN, ktoré je chránené proti automatickému prístupu. Detail parcely preto na Nahlížení do KN
iba odkazuje.

## Vývoj

```bash
docker compose exec php composer test    # PHPUnit (unit + integračné testy proti DB)
docker compose exec php composer stan    # PHPStan, úroveň max
docker compose exec php composer cs      # kontrola štýlu (PER-CS); composer cs:fix opraví
```

Po zmene PHP kódu treba `docker compose restart php`: OPcache nekontroluje časy súborov, lebo každá kontrola
na bind mounte z disku Windowsu stojí ~9 ms na súbor. Testy a CLI skripty vidia zmeny hneď.

Po zmene kódu, ktorý kreslí dlaždice alebo hranice, treba zmazať aj ich cache (je verzovaná dátumom dát, nie
kódu): `docker compose exec -u www-data php php bin/clear-cache.php --all`. Zmeny v `public/` (HTML, JS, CSS)
sa prejavia po obnovení stránky, nginx ich posiela s `Cache-Control: no-cache`.

## Štruktúra

```
bin/          CLI skripty (zoznam obcí, sťahovanie, import číselníkov a VFR, mazanie starej cache)
data/         zoznam obcí okresu; data/raw = stiahnuté VFR (volume)
db/           init/ = SQL pri vytvorení databázy, transform.sql = staging → tabuľky aplikácie
docker/       Dockerfile a konfigurácia PHP-FPM, nginx a GDAL
docs/         zápisník rozhodnutí, časový log, schéma VFR
public/       front controller (index.php) a frontend (index.html, assets/app.js, assets/app.css)
src/          PHP kód (namespace Parcely\, PSR-4): Http, Controller (bez SQL), Repository a Tile/TileRenderer
              (jediné miesta so SQL), Tile (súradnice, cache), Domain (DTO, číslo parcely), Cli, Vfr, Download, Import
tests/        PHPUnit testy
```

## Služby (Docker Compose)

| Služba | Image | Úloha |
|---|---|---|
| `db` | `postgis/postgis:18-3.6` | PostgreSQL 18 + PostGIS 3.6 |
| `php` | vlastný (`php:8.3.36-fpm-alpine`) | PHP-FPM s `pdo_pgsql` a OPcache |
| `web` | `nginx:1.28.3-alpine` | statické súbory, ostatné cez front controller |
| `gdal` | vlastný (`ghcr.io/osgeo/gdal:ubuntu-small-3.13.3` + `psql` 18) | import VFR (`ogr2ogr`, `psql`), spúšťa sa iba ručne (`docker compose run --rm gdal …`) |

Kód sa do kontajnerov pripája z disku (bind mount). Stiahnuté dáta (`data/raw`, volume `raw-data`,
zdieľaný medzi `php` a `gdal`) a `var/` (volume `app-var`: cache dlaždíc, logy) sú v pomenovaných
Docker volumes, lebo na Windows + WSL 2 je bind mount z disku Windowsu výrazne pomalší. Na disku
ich preto neuvidíš, napríklad `docker compose exec php ls data/raw` ich vypíše.

Príkazy v README sú pre PowerShell. V Git Bash treba pred príkazy s cestou typu `/vsizip/…` dať
`MSYS_NO_PATHCONV=1`, inak ich Git Bash prepíše na cestu Windowsu.

## Zdroj dát

Dáta RÚIAN © ČÚZK, licencia [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
