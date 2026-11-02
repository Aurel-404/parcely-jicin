# Parcely okresu Jičín

Webová mapa všetkých 271 995 parciel okresu Jičín. Po kliknutí na parcelu sa zobrazia jej údaje: číslo,
katastrálne územie, výmera, druh pozemku, stavby a odkaz do Nahlížení do katastru. Dáta sú otvorené dáta RÚIAN
od ČÚZK, backend je v čistom PHP.

| Desktop | Telefón |
|---|---|
| ![Mapa s detailom parcely na počítači](docs/img/desktop.jpg) | <img src="docs/img/mobile.jpg" alt="Mapa s detailom parcely na telefóne" width="260"> |

## Spustenie

Treba len Docker s Compose v2 (Docker Desktop alebo Docker Engine), voľné porty `8080` a `5433`, internet
a asi 3 GB miesta. PHP 8.3, PostgreSQL 18 s PostGIS 3.6, nginx aj GDAL bežia v kontajneroch, lokálne ich
inštalovať netreba.

```bash
git clone https://github.com/Aurel-404/parcely-jicin.git
cd parcely-jicin
cp .env.example .env
docker compose up -d --build --wait                  # prvý štart, asi 3 min
docker compose exec php php bin/download.php          # dáta 111 obcí, asi 1 min
docker compose exec php php bin/import-codelists.php  # číselníky ČÚZK
docker compose run --rm gdal bin/import.sh            # import, asi 1 min
# otvor http://localhost:8080
```

- Hodnoty v `.env` netreba meniť. Heslo databázy sa použije len pri jej prvom vytvorení, neskoršia zmena sa
  prejaví až po `docker compose down -v` (zmaže aj dáta).
- `download.php` stiahne najnovšiu mesačnú sadu, takže počty sa môžu od tohto README mierne líšiť. Čísla tu
  platia pre sadu 20260930, tú istú sadu stiahne `--date=20260930`, kým ju ČÚZK ponúka.

## Čo aplikácia vie

- Pri štarte celý okres s hranicami a názvami katastrálnych území, parcely po priblížení.
- Parcely sú zafarbené podľa druhu pozemku, legenda ukazuje názvy z číselníka ČÚZK a počty parciel.
- Klik na parcelu: číslo, KÚ, obec, výmera, druh pozemku, spôsob využitia, stavby a odkazy do Nahlížení do KN
  a do RÚIAN.
- Hľadanie podľa katastrálneho územia a čísla parcely, napr. `Jičín` + `st. 1868/26`.
- Odkaz na parcelu sa dá poslať ďalej, napr. `http://localhost:8080/#parcel=1755256604`.
- Podklad OpenStreetMap alebo ortofoto ČÚZK, funguje aj na telefóne. Texty v aplikácii sú po česky.

## Ako to funguje

1. Raz za mesiac stiahnem z ČÚZK dáta RÚIAN pre 111 obcí okresu a nahrám ich do PostgreSQL s PostGIS.
2. PHP z databázy vyrába vektorové dlaždice: pre každý štvorček mapy len parcely, ktoré v ňom ležia. Hotovú
   dlaždicu uloží na disk, takže ďalšie zobrazenie už databázu nepotrebuje.
3. V prehliadači ich skladá a kreslí knižnica MapLibre cez grafickú kartu (WebGL), preto mapa ostáva plynulá.
4. Klik na parcelu pošle jeden dotaz na API, ktoré vráti jej detail z databázy.

Architektúra, API, merania výkonu, testy a pokyny pre vývoj sú v [docs/technicke-detaily.md](docs/technicke-detaily.md).

## Rozhodnutia

Pri každom kroku som zvažoval viac možností. Najdôležitejšie rozhodnutia:

- **Dáta stiahnuť, nie ťahať live.** Služba WFS od ČÚZK vracia len obmedzený počet prvkov, celý okres by
  plynulo nezobrazila. RÚIAN sa mení raz mesačne, takže stačí dáta raz za mesiac stiahnuť.
- **Celý okres, nie len minimum.** Zadanie vyžadovalo KÚ Jičín a 3 ďalšie, spracoval som všetkých 240 KÚ
  a 111 obcí.
- **Vektorové dlaždice namiesto jedného veľkého súboru.** Prehliadač dostane len to, čo je práve na obrazovke,
  nie 272 tisíc polygónov naraz.
- **Čisté PHP bez frameworku**, ako zadanie preferuje. Composer používam len na autoload a nástroje na kontrolu
  kódu.
- **Vlastníkov neukazujem**, dáta RÚIAN ich neobsahujú. Detail parcely má namiesto toho odkaz do Nahlížení do KN.

**Ako som chápal „plynule aj pri zobrazení celého okresu“.** Mapa musí byť plynulá nad celým okresom, no nemusí
kresliť každú parcelu aj pri najmenšom priblížení. Pri pohľade na celý okres má priemerná parcela (3 260 m²)
na obrazovke len 1–2 pixely a hranice by splynuli do jednej plochy. Preto vtedy ukazujem hranice a názvy
katastrálnych území a parcely až od zoomu 13 (výrez asi 8 × 5 km). Zoom 13 som neodhadol, ale zmeral: pri
zoome 12 by mala najväčšia dlaždica už 889 kB. Filter podľa výmery som zamietol, lebo mapa by tvrdila, že malé
parcely neexistujú.

## Čo som pridal navyše

Okrem povinného zadania som hľadal, čo by mapu urobilo užitočnejšou pri práci s pozemkami:

- **Farby podľa druhu pozemku** s legendou a počtami parciel, klik v legende zvýrazní jeden druh. Farby som
  overil tak, aby sa dali rozlíšiť aj pri poruche farebného videnia. Preto majú vlastnú farbu len hlavné druhy
  a príbuzné druhy (zahrada, ovocný sad, vinice, chmelnice) farbu zdieľajú.
- **Hľadanie parcely** podľa katastrálneho územia a čísla, s našepkávaním.
- **Ortofoto ČÚZK** ako druhý podklad.
- **Odkaz na parcelu**, ktorý sa dá poslať ďalej, a zobrazenie na telefóne.
- **Merania výkonu:** benchmark dlaždíc nad celým okresom, podľa neho som nastavil zoom 13. Hotové dlaždice
  sa ukladajú na disk.
- **Mesačná aktualizácia** štyrmi príkazmi (nižšie), neúspešný import nič nepokazí.
- **Testy a kontrola kódu:** 138 testov, PHPStan na najvyššej úrovni a jednotný štýl kódu.

## Mesačná aktualizácia

```bash
docker compose exec php php bin/download.php          # nová sada
docker compose exec php php bin/import-codelists.php  # číselníky (ČÚZK mohol pridať kód)
docker compose run --rm gdal bin/import.sh            # import, asi 1 min
docker compose exec php php bin/clear-cache.php       # zmaže cache predchádzajúcej sady
```

Import beží v jednej transakcii: nikto neuvidí polovičné dáta a neúspešný import starú sadu nepokazí.

## Zápisník

**Čo ma prekvapilo**

- ČÚZK presunul súbory na novú doménu `vdp.cuzk.gov.cz` a číselníky nie sú tam, kde ostatné súbory
  (`specialni/` namiesto `soucasna/`).
- 717 parciel má v hranici kruhové oblúky, import ich nahrádza krátkymi úsečkami.
- Docker na Windows číta súbory z disku Windowsu pomaly, PHP tak strácalo pri požiadavke až 1,7 s. Pomohlo
  nastavenie OPcache a autoloadera.
- `ogrinfo` nevybral obce okresu ani za 15 minút, PHP s `XMLReader` to zvládne za 3 s.
- Nahlížení do KN má ochranu proti botom, dve z troch kontrolných parciel som preto overil ručne.
- 9 druhov pozemku sa na mape farbou spoľahlivo rozlíšiť nedá.

**Čo by som robil ďalej alebo inak**

- Denné zmeny z ČÚZK namiesto mesačnej úplnej kópie.
- Automatické testy pri každom pushi (GitHub Actions), aj v prehliadači.
- Prehľadová vrstva s druhmi pozemkov pre pohľad na celý okres.
- Pre celú ČR predgenerované dlaždice alebo samostatný tile server.
- Hľadanie bez diakritiky.

**Čas:** asi 5–6 hodín vrátane rozhodovania (zadanie odhadovalo 4–12 hodín).

## Použitie AI

Plán a zadania po fázach som pripravil s AI asistentom Claude, kód, testy a dokumentáciu podľa nich písal
Claude Code. Každú fázu a každé rozhodnutie som schvaľoval a výsledky som kontroloval.

## Licencie a atribúcia

- Dáta: ČÚZK, RÚIAN, licencia [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/); ortofoto © ČÚZK.
- Podkladová mapa © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors.
- Písmo popiskov v `public/fonts`: glyfy Noto Sans Regular zo servera `demotiles.maplibre.org` projektu
  MapLibre, stiahnuté 2026-10-31. Písmo © The Noto Project Authors, licencia SIL Open Font License 1.1
  ([public/fonts/OFL.txt](public/fonts/OFL.txt)).
