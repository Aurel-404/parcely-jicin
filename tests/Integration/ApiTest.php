<?php

declare(strict_types=1);

namespace Parcely\Tests\Integration;

use Parcely\Application;
use Parcely\Config;
use Parcely\Database\Database;
use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Tile\Envelope;
use Parcely\Tile\ParcelTiles;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The HTTP API against the imported data, with the tile cache in a temporary folder.
 */
final class ApiTest extends IntegrationTestCase
{
    private Database $db;
    private string $varDirectory;
    private Application $app;

    protected function setUp(): void
    {
        $config = self::config();
        $this->db = Database::fromConfig($config);
        self::requireImportedData($this->db);

        $this->varDirectory = sys_get_temp_dir() . '/' . uniqid('parcely', true);
        $this->app = new Application(new Config(
            $config->appEnv,
            $config->dbHost,
            $config->dbPort,
            $config->dbName,
            $config->dbUser,
            $config->dbPassword,
            $config->vfrBaseUrl,
            $this->varDirectory,
        ), $this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->varDirectory) && is_dir($this->varDirectory)) {
            exec('rm -rf ' . escapeshellarg($this->varDirectory));
        }
    }

    public function testTileOverJicinIsRenderedOnceThenServedFromCache(): void
    {
        $miss = $this->get('/tiles/parcels/14/8892/5526.pbf');
        $hit = $this->get('/tiles/parcels/14/8892/5526.pbf');

        self::assertSame(200, $miss->status);
        self::assertSame('application/vnd.mapbox-vector-tile', $miss->headers['Content-Type']);
        self::assertSame(['MISS', 'HIT'], [$miss->headers['X-Cache'], $hit->headers['X-Cache']]);
        $bytes = self::content($hit);
        self::assertGreaterThan(1000, strlen($bytes));
        // An MVT starts with field 3 (layer), wire type 2: byte 0x1A; the layer is called "parcels".
        self::assertSame("\x1A", $bytes[0]);
        self::assertStringContainsString('parcels', $bytes);
        self::assertSame(sprintf('"%s-14-8892-5526"', $this->vfrDate()), $hit->headers['ETag']);
    }

    public function testTileJsonVersionsTileUrlsByDataSet(): void
    {
        $tileJson = $this->json('/tiles/parcels.json');

        self::assertSame(['/tiles/parcels/{z}/{x}/{y}.pbf?v=' . $this->vfrDate()], $tileJson['tiles']);
        self::assertSame('no-cache', $this->get('/tiles/parcels.json')->headers['Cache-Control']);
    }

    public function testKnownEtagGives304(): void
    {
        $etag = sprintf('"%s-14-8892-5526"', $this->vfrDate());

        $response = $this->app->handle(new Request('GET', '/tiles/parcels/14/8892/5526.pbf', headers: ['if-none-match' => 'W/' . $etag]));

        self::assertSame(304, $response->status);
        self::assertSame($etag, $response->headers['ETag']);
    }

    /**
     * Checked by hand against Nahlížení do KN.
     *
     * @return iterable<string, array{int, string, int, string}>
     */
    public static function verifiedParcels(): iterable
    {
        yield 'building plot in Jičín' => [1755256604, 'st. 1868/26', 19, 'Jičín'];
        yield 'land plot in Chlum u Hořic' => [48392061010, '740/11', 15753, 'Chlum u Hořic'];
        yield 'forest plot in Brtev' => [1689612604, '231/3', 8, 'Brtev'];
    }

    #[DataProvider('verifiedParcels')]
    public function testParcelDetail(int $id, string $label, int $area, string $cadastralArea): void
    {
        $parcel = $this->json('/api/parcels/' . $id);

        self::assertSame($id, $parcel['id']);
        self::assertSame($label, $parcel['label']);
        self::assertSame($area, $parcel['vymera_m2']);
        self::assertSame($cadastralArea, self::path($parcel, 'ku', 'nazev'));
        self::assertSame('https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=' . $id, self::path($parcel, 'links', 'nahlizeni_kn'));
        $dataDate = self::path($parcel, 'data_k');
        self::assertIsString($dataDate);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dataDate);
        self::assertIsArray($parcel['bbox']);
        self::assertIsArray($parcel['centroid']);
        [$minLon, $minLat, $maxLon, $maxLat] = $parcel['bbox'];
        [$lon, $lat] = $parcel['centroid'];
        self::assertTrue($minLon <= $lon && $lon <= $maxLon && $minLat <= $lat && $lat <= $maxLat, 'centroid inside bbox');
    }

    public function testParcelDetailListsTheBuildingOnIt(): void
    {
        // Nahlížení do KN: "Budova bez čísla popisného nebo evidenčního: garáž".
        $parcel = $this->json('/api/parcels/1755256604');

        self::assertSame('garáž', self::path($parcel, 'stavby', 0, 'zpusob_vyuziti', 'nazev'));
        self::assertSame(3, self::path($parcel, 'stavby', 0, 'typ', 'kod'));
        self::assertNull(self::path($parcel, 'stavby', 0, 'oznaceni'));
    }

    public function testUnknownParcelGives404(): void
    {
        self::assertSame(404, $this->get('/api/parcels/1')->status);
    }

    public function testSearchByCadastralAreaNameOrCode(): void
    {
        foreach (['Jičín', 'jičín', '659541'] as $cadastralArea) {
            $results = $this->json('/api/search', ['ku' => $cadastralArea, 'cislo' => 'st. 1868/26'])['results'];

            self::assertIsArray($results);
            self::assertCount(1, $results, $cadastralArea);
            self::assertSame(1755256604, self::path($results, 0, 'id'));
        }
    }

    public function testSearchWithoutPrefixListsLandPlotsFirst(): void
    {
        $results = $this->json('/api/search', ['ku' => 'Jičín', 'cislo' => '1868'])['results'];
        self::assertIsArray($results);
        $labels = array_column($results, 'label');

        self::assertCount(10, $labels, 'limit');
        self::assertSame('1868', $labels[0]);
        self::assertSame('st. 1868/1', $labels[1]);
    }

    public function testSearchTreatsInputAsValueNotSql(): void
    {
        $results = $this->json('/api/search', ['ku' => "Jičín' OR '1'='1", 'cislo' => '1'])['results'];

        self::assertSame([], $results);
    }

    public function testCadastralAreaSuggestions(): void
    {
        $results = $this->json('/api/ku', ['q' => 'jič'])['results'];

        self::assertSame('Jičín', self::path($results, 0, 'nazev'));
        self::assertSame('Jičín', self::path($results, 0, 'obec', 'nazev'));
        self::assertSame([], $this->json('/api/ku', ['q' => '%'])['results'], '% is a literal character');
    }

    public function testBoundariesContainDistrictMunicipalitiesAndCadastralAreas(): void
    {
        $response = $this->get('/api/boundaries.geojson');
        self::assertSame('application/geo+json', $response->headers['Content-Type']);
        $collection = json_decode(self::content($response), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($collection);
        self::assertIsArray($collection['features']);

        $types = [];
        foreach ($collection['features'] as $feature) {
            $type = self::path($feature, 'properties', 'type');
            self::assertIsString($type);
            $types[$type] = ($types[$type] ?? 0) + 1;
        }
        $cadastralAreas = $this->db->fetchValue('SELECT count(*) FROM ku');
        self::assertSame(
            ['okres' => 1, 'obec' => $this->db->fetchValue('SELECT count(*) FROM obec'), 'ku' => $cadastralAreas, 'ku_label' => $cadastralAreas],
            $types,
        );
        self::assertSame('HIT', $this->get('/api/boundaries.geojson')->headers['X-Cache']);
    }

    public function testDistrictExtentConstantCoversImportedDistrict(): void
    {
        $row = $this->db->fetchRow('SELECT ST_XMin(e) AS min_x, ST_YMin(e) AS min_y, ST_XMax(e) AS max_x, ST_YMax(e) AS max_y FROM (SELECT ST_Extent(geom) AS e FROM okres) s');
        self::assertNotNull($row);

        $imported = new Envelope($row->float('min_x'), $row->float('min_y'), $row->float('max_x'), $row->float('max_y'));

        self::assertTrue((new Envelope(...ParcelTiles::DISTRICT_EXTENT))->contains($imported), 'Update ParcelTiles::DISTRICT_EXTENT');
    }

    /**
     * @param array<string, string> $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query));
    }

    /**
     * @param array<string, string> $query
     * @return array<mixed>
     */
    private function json(string $path, array $query = []): array
    {
        $response = $this->get($path, $query);
        self::assertSame(200, $response->status, $response->body);
        $data = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private static function content(Response $response): string
    {
        self::assertNotNull($response->file);

        return (string) file_get_contents($response->file);
    }

    /**
     * Value at a path in decoded JSON, e.g. path($parcel, 'ku', 'nazev').
     */
    private static function path(mixed $data, string|int ...$keys): mixed
    {
        foreach ($keys as $key) {
            self::assertIsArray($data);
            self::assertArrayHasKey($key, $data);
            $data = $data[$key];
        }

        return $data;
    }

    private function vfrDate(): string
    {
        $date = $this->db->fetchValue("SELECT value FROM meta WHERE key = 'vfr_date'");
        self::assertIsString($date);

        return $date;
    }
}
