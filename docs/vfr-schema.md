# Schéma VFR (overená)

Výpis polí zo skutočných súborov sady **20260930** (číselníky **20261003**), GDAL 3.13.3, ovládač GML so
šablónou `ruian_vf_ob_v1.gfs`. Nič tu nie je prevzaté z dokumentácie, všetko je z `ogrinfo` alebo z XML.

```bash
docker compose run --rm gdal ogrinfo -ro -so /vsizip/data/raw/20260930_OB_572659_UKSH.xml.zip
docker compose run --rm gdal ogrinfo -ro -so /vsizip/data/raw/20260930_OB_572659_UKSH.xml.zip Parcely
```

Súradnicový systém všetkých geometrií: **S-JTSK / Krovak East North, EPSG:5514** (`ogrinfo` aj `srid` po importe).

## Vrstvy súboru obce (`OB_{kód}_UKSH`)

| Vrstva | Geometrie | Importuje sa |
|---|---|---|
| Obce | Multi Point, Multi Polygon, Multi Polygon | áno → `obec` |
| SpravniObvody | Point, Multi Polygon | nie |
| Mop | Point, Multi Polygon | nie |
| Momc | Point, Multi Polygon | nie |
| CastiObci | Point | nie |
| KatastralniUzemi | Multi Point, Multi Polygon, Multi Polygon | áno → `ku` |
| Zsj | Multi Point, Multi Polygon | nie |
| Ulice | Multi Line String | nie |
| **Parcely** | Point, Polygon, Multi Polygon | áno → `parcel` |
| StavebniObjekty | Point, Multi Polygon, Multi Polygon | áno → `building` |
| AdresniMista | Point, Point, Point | nie |

Hranica okresu je v štátnom súbore `ST_UKSH`, vrstva `Okresy` (76 okresov).

## Parcely

Geometrie: `DefinicniBod` (Point), `OriginalniHranice` (Polygon), `OriginalniHraniceOmpv` (Multi Polygon).
Obec Jičín má 16 953 parciel, celý okres 271 995.

| Pole (ogrinfo) | Typ | Cieľ v `parcel` |
|---|---|---|
| gml_id | String NOT NULL | – |
| **Id** | Integer64 | `id` (kód parcely, ten istý ako v Nahlížení do KN) |
| Nespravny | String | – (v okrese 0× `true`) |
| **KmenoveCislo** | Integer | `kmenove_cislo` |
| **PododdeleniCisla** | Integer | `poddeleni` |
| **VymeraParcely** | Integer64 | `vymera_m2` |
| **ZpusobyVyuzitiPozemku** | Integer | `zpusob_vyuziti_kod` (napriek množnému číslu je to jeden kód) |
| **DruhCislovaniKod** | Integer | `druh_cislovani_kod`, z neho prefix „st.“ v `label` |
| **DruhPozemkuKod** | Integer | `druh_pozemku_kod` |
| **KatastralniUzemiKod** | Integer | `ku_kod` |
| PlatiOd, PlatiDo | String | – (`PlatiDo` je v okrese vždy prázdne) |
| IdTransakce, RizeniId | Integer64 | – |
| BonitovanyDilVymera, BonitovanyDilBonitovanaJednotkaKod | IntegerList | – |
| BonitovanyDilIdTranskace, BonitovanyDilRizeniId | Integer64List | – (preklep `IdTranskace` je priamo v XML ČÚZK) |
| ZpusobOchranyKod, ZpusobOchranyTypOchranyKod, ZpusobOchranyIdTransakce | IntegerList | – |
| ZpusobOchranyRizeniId | Integer64List | – |
| **OriginalniHranice** | geometria | `geom` (EPSG:3857) |
| DefinicniBod | geometria | – |
| OriginalniHraniceOmpv | geometria | – (v okrese ju nemá žiadna parcela) |

O `OriginalniHraniceOmpv` hovorí hlavička súborov VFR len „doplnění hranice pro parcely OMP-V
(OriginalniHraniceOmpv)“. V sade 20260930 ju v okrese nemá žiadna parcela ani stavba a všetkých 271 995 parciel
má `OriginalniHranice`.

## KatastralniUzemi

Geometrie: `DefinicniBod`, `OriginalniHranice`, `GeneralizovaneHranice`. V okrese 240 katastrálnych území.

| Pole | Typ | Cieľ v `ku` |
|---|---|---|
| **Kod** | Integer | `kod` |
| **Nazev** | String(48) | `nazev` |
| **ObecKod** | Integer | `obec_kod` |
| Nespravny, ExistujeDigitalniMapa | String | – |
| PlatiOd, PlatiDo, DatumVzniku | String | – |
| IdTransakce, GlobalniIdNavrhuZmeny, RizeniId | Integer64 | – |
| MluvnickeCharakteristikyPad2 … Pad7 | String | – |
| **OriginalniHranice** | geometria | `geom` |

## Obce

Geometrie: `DefinicniBod`, `OriginalniHranice`, `GeneralizovaneHranice`. Súbor obce obsahuje práve jednu obec.

| Pole | Typ | Cieľ v `obec` |
|---|---|---|
| **Kod** | Integer | `kod` |
| **Nazev** | String(48) | `nazev` |
| **OkresKod** | Integer | – (podľa neho sa vyberie okres) |
| Nespravny, StatusKod, PouKod | | – |
| PlatiOd, PlatiDo, DatumVzniku, IdTransakce, GlobalniIdNavrhuZmeny | | – |
| MluvnickeCharakteristikyPad2 … Pad7 | String | – |
| VlajkaText, VlajkaObrazek, ZnakText, ZnakObrazek | String | – |
| CleneniSMRozsahKod, CleneniSMTypKod, NutsLau | | – |
| **OriginalniHranice** | geometria | `geom` |

## StavebniObjekty

Geometrie: `DefinicniBod`, `OriginalniHranice`, `OriginalniHraniceOmpv`. V okrese 45 462 stavieb, 45 307 z nich
stojí na parcele v okrese (14 nemá identifikačnú parcelu, 141 stojí na parcele mimo okresu).

| Pole | Typ | Cieľ v `building` |
|---|---|---|
| **Kod** | Integer | `id` |
| **IdentifikacniParcelaId** | Integer64 | `parcel_id` |
| **CisloDomovni** | IntegerList | `cisla_domovni` (stavba môže mať viac čísel) |
| **TypStavebnihoObjektuKod** | Integer | `typ_kod` (č. p. / č. ev. / bez čísla) |
| **ZpusobVyuzitiKod** | Integer | `zpusob_vyuziti_kod` |
| Nespravny, CastObceKod, MomcKod, IsknBudovaId | | – |
| PlatiOd, PlatiDo, IdTransakce, GlobalniIdNavrhuZmeny, Dokonceni | | – |
| DruhKonstrukceKod, ObestavenyProstor, PocetBytu, PocetPodlazi, PodlahovaPlocha, ZastavenaPlocha | Integer | – |
| PripojeniKanalizaceKod, PripojeniPlynKod, PripojeniVodovodKod, VybaveniVytahemKod, ZpusobVytapeniKod | Integer | – |
| ZpusobOchrany… (4 polia), DetailniTEA… (11 polí) | zoznamy | – |

## Okresy (`ST_UKSH`)

| Pole | Typ | Cieľ v `okres` |
|---|---|---|
| **Kod** | Integer | `kod` (Jičín = 3604) |
| **Nazev** | String(32) | `nazev` |
| Nespravny, KrajKod, VuscKod, NutsLau | | – |
| PlatiOd, PlatiDo, DatumVzniku, IdTransakce, GlobalniIdNavrhuZmeny | | – |
| **OriginalniHranice** | geometria | `geom` |

## Číselníky (`ST_UCIS`)

Súbor obsahuje 25 číselníkov v tvare `<vf:DruhyPozemku><vfc:DruhPozemku><vfc:Kod>…</vfc:Kod><vfc:Nazev>…</vfc:Nazev>…`.
Importuje sa týchto 5 (`bin/import-codelists.php`):

| Číselník v XML | Tabuľka | Počet kódov |
|---|---|---|
| DruhyCislovaniParcel | `cis_druh_cislovani` | 2 |
| DruhyPozemku | `cis_druh_pozemku` | 11 |
| ZpusobyVyuzitiPozemku | `cis_zpusob_vyuziti` | 30 |
| TypyStavebnichObjektu | `cis_typ_stavebniho_objektu` | 3 |
| ZpusobyVyuzitiObjektu | `cis_zpusob_vyuziti_objektu` | 30 |

Ostatné: DruhyKonstrukce, PripojeniKanalizace, PripojeniPlynu, PripojeniVody, VybaveniVytahu, ZpusobyVytapeni,
CharakteryZSJ, StatusObci, CleneniSmRozsahu, CleneniSmTypu, Bpej (3 035), TypyOchrany, ZpusobyOchrany, TypyPrvku,
AdresniPosty (2 677), SubtypyUUP, TypyUUP, ZpusobyVymezeniUUP, SkupinyNerostu, KategorieUcelovychPrvku.

**Druh číslovania parciel:** 1 = Stavební, 2 = Pozemková.

**Typ stavebného objektu:** 1 = budova s číslem popisným, 2 = budova s číslem evidenčním, 3 = budova bez č.p. i č.ev.

**Druh pozemku:** 2 = orná půda, 3 = chmelnice, 4 = vinice, 5 = zahrada, 6 = ovocný sad, 7 = trvalý travní porost,
8 = trvalý travní porost, 10 = lesní pozemek, 11 = vodní plocha, 13 = zastavěná plocha a nádvoří, 14 = ostatní plocha.
(Kódy 7 a 8 majú v súbore rovnaký názov.)

## Čo prekvapilo

- **Oblúky:** 717 parciel v okrese má v hranici kruhové oblúky (1 271 `gml:ArcString`, 54 `gml:Circle`). Import
  ich aproximuje úsečkami (`ogr2ogr -nlt CONVERT_TO_LINEAR`).
- **Viac geometrií na prvok:** parcela má tri geometrické stĺpce, stavba tiež, obec a KÚ majú navyše generalizované
  hranice.
- **Názvy polí:** `ZpusobyVyuzitiPozemku` je jeden kód, nie zoznam. Element `com:IdTranskace` (s preklepom) je
  priamo v XML ČÚZK (8 625× v súbore obce Jičín) a šablóna GDAL `ruian_vf_ob_v1.gfs` ho preberá ako
  `BonitovanyDilIdTranskace`.
- **Číselníky** nie sú v adresári `soucasna/` ako ostatné súbory, ale v `specialni/`.
