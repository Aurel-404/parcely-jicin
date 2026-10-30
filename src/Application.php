<?php

declare(strict_types=1);

namespace Parcely;

use Parcely\Controller\BoundaryController;
use Parcely\Controller\HealthController;
use Parcely\Controller\ParcelController;
use Parcely\Controller\SearchController;
use Parcely\Controller\TileController;
use Parcely\Database\Database;
use Parcely\Http\HttpException;
use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Http\Router;
use Parcely\Repository\BoundaryRepository;
use Parcely\Repository\DataNotImported;
use Parcely\Repository\MetaRepository;
use Parcely\Repository\ParcelRepository;
use Parcely\Tile\ParcelTiles;
use Parcely\Tile\TileCache;
use Parcely\Tile\TileRenderer;

/**
 * Wires routes to controllers and turns every failure into a JSON error response.
 */
final readonly class Application
{
    private Router $router;

    public function __construct(private Config $config, Database $db)
    {
        $meta = new MetaRepository($db);
        $parcels = new ParcelRepository($db);
        $boundaries = new BoundaryRepository($db);
        $cache = new TileCache($config->varDirectory . '/tiles');

        $health = new HealthController($db);
        $tiles = new TileController(new ParcelTiles(new TileRenderer($db), $cache), $meta);
        $parcel = new ParcelController($parcels);
        $search = new SearchController($parcels, $boundaries);
        $boundary = new BoundaryController($boundaries, $cache, $meta);

        $this->router = new Router();
        $this->router->get('/api/health', $health->check(...));
        $this->router->get('/tiles/parcels.json', $tiles->tileJson(...));
        $this->router->get('/tiles/parcels/{z}/{x}/{y}.pbf', $tiles->parcels(...));
        $this->router->get('/api/parcels/{id}', $parcel->detail(...));
        $this->router->get('/api/search', $search->parcels(...));
        $this->router->get('/api/ku', $search->cadastralAreas(...));
        $this->router->get('/api/boundaries.geojson', $boundary->geojson(...));
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return Response::json(['error' => $e->getMessage()], $e->status, $e->headers);
        } catch (DataNotImported $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        } catch (\PDOException $e) {
            // 42P01 = undefined_table: the import has never run.
            if ($e->getCode() === '42P01') {
                return Response::json(['error' => (new DataNotImported())->getMessage()], 503);
            }

            return $this->internalError($e);
        } catch (\Throwable $e) {
            return $this->internalError($e);
        }
    }

    private function internalError(\Throwable $e): Response
    {
        // Logged with the stack trace but without arguments (zend.exception_ignore_args), so no secrets.
        error_log((string) $e);
        $message = $this->config->isDebug() ? $e->getMessage() : 'Internal server error';

        return Response::json(['error' => $message], 500);
    }
}
