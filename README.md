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

Mesačná aktualizácia = `bin/download.php` a potom oba príkazy importu. Schéma `staging` (surové vrstvy, 184 MB)
po importe ostáva na kontrolu dát SQL dotazmi a ďalší import ju zahodí.

Výsledok pre sadu 20260930:

| Tabuľka | Počet |
|---|---|
| `obec` | 111 |
| `ku` (katastrálne územia) | 240 |
| `parcel` | 271 995 |
| `building` (stavby na parcelách okresu) | 45 307 |

Súčet výmer parciel (886,86 km²) sedí s plochou okresu (886,83 km²). Polia VFR a ich mapovanie na tabuľky sú
v [docs/vfr-schema.md](docs/vfr-schema.md).

## Vývoj

```bash
docker compose exec php composer test    # PHPUnit (unit + integračné testy proti DB)
docker compose exec php composer stan    # PHPStan, úroveň max
docker compose exec php composer cs      # kontrola štýlu (PER-CS); composer cs:fix opraví
```

## Štruktúra

```
bin/          CLI skripty (zoznam obcí, sťahovanie, import číselníkov a VFR)
data/         zoznam obcí okresu; data/raw = stiahnuté VFR (volume)
db/           init/ = SQL pri vytvorení databázy, transform.sql = staging → tabuľky aplikácie
docker/       Dockerfile a konfigurácia PHP-FPM, nginx a GDAL
docs/         zápisník rozhodnutí, časový log, schéma VFR
public/       front controller (index.php) a statický frontend
src/          PHP kód (namespace Parcely\, PSR-4)
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
