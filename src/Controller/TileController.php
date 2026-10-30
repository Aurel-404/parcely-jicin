<?php

declare(strict_types=1);

namespace Parcely\Controller;

use Parcely\Http\HttpException;
use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Repository\MetaRepository;
use Parcely\Tile\ParcelTiles;
use Parcely\Tile\TileCoordinate;

final readonly class TileController
{
    private const string CONTENT_TYPE = 'application/vnd.mapbox-vector-tile';
    private const string CACHE_CONTROL = 'public, max-age=86400';

    public function __construct(
        private ParcelTiles $tiles,
        private MetaRepository $meta,
    ) {}

    /**
     * GET /tiles/parcels/{z}/{x}/{y}.pbf. An empty tile is 200 with an empty body, like any other tile: the
     * same headers and caching, and MapLibre reads it as a tile without features.
     *
     * @param array<string, string> $params
     */
    public function parcels(Request $request, array $params): Response
    {
        try {
            $tile = TileCoordinate::fromStrings($params['z'], $params['x'], $params['y']);
        } catch (\InvalidArgumentException $e) {
            throw HttpException::badRequest($e->getMessage());
        }

        // Empty for sure (below the minimum zoom or outside the district): answered without the database.
        if (!$this->tiles->mayContainParcels($tile)) {
            return new Response(200, ['Content-Type' => self::CONTENT_TYPE, 'Cache-Control' => self::CACHE_CONTROL]);
        }

        $vfrDate = $this->meta->vfrDate();
        $headers = [
            'Cache-Control' => self::CACHE_CONTROL,
            'ETag' => sprintf('"%s-%d-%d-%d"', $vfrDate, $tile->z, $tile->x, $tile->y),
        ];
        if ($request->hasEtag($headers['ETag'])) {
            return Response::notModified($headers);
        }

        $file = $this->tiles->get($tile, $vfrDate);

        return Response::file($file->path, self::CONTENT_TYPE, $headers + ['X-Cache' => $file->hit ? 'HIT' : 'MISS']);
    }
}
