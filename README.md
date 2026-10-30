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

## Vývoj

```bash
docker compose exec php composer test    # PHPUnit (unit + integračné testy proti DB)
docker compose exec php composer stan    # PHPStan, úroveň max
docker compose exec php composer cs      # kontrola štýlu (PER-CS); composer cs:fix opraví
```

## Štruktúra

```
bin/          CLI skripty (zoznam obcí, sťahovanie dát)
data/         zoznam obcí okresu; data/raw = stiahnuté VFR (volume)
db/init/      SQL spustené pri vytvorení databázy
docker/       Dockerfile a konfigurácia PHP-FPM a nginx
docs/         zápisník rozhodnutí a časový log
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
| `gdal` | `ghcr.io/osgeo/gdal:ubuntu-small-3.13.3` | nástroj na import VFR, spúšťa sa iba ručne (`docker compose run --rm gdal …`) |

Kód sa do kontajnerov pripája z disku (bind mount). Stiahnuté dáta (`data/raw`, volume `raw-data`,
zdieľaný medzi `php` a `gdal`) a `var/` (volume `app-var`: cache dlaždíc, logy) sú v pomenovaných
Docker volumes, lebo na Windows + WSL 2 je bind mount z disku Windowsu výrazne pomalší. Na disku
ich preto neuvidíš, napríklad `docker compose exec php ls data/raw` ich vypíše.

Príkazy v README sú pre PowerShell. V Git Bash treba pred príkazy s cestou typu `/vsizip/…` dať
`MSYS_NO_PATHCONV=1`, inak ich Git Bash prepíše na cestu Windowsu.

## Zdroj dát

Dáta RÚIAN © ČÚZK, licencia [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
