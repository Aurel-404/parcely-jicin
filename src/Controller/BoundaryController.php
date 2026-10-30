<?php

declare(strict_types=1);

namespace Parcely\Controller;

use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Repository\BoundaryRepository;
use Parcely\Repository\MetaRepository;
use Parcely\Tile\TileCache;

final readonly class BoundaryController
{
    public function __construct(
        private BoundaryRepository $boundaries,
        private TileCache $cache,
        private MetaRepository $meta,
    ) {}

    /**
     * GET /api/boundaries.geojson: rendered once per data set (~0.3 s), then served from the cache.
     */
    public function geojson(Request $request): Response
    {
        $vfrDate = $this->meta->vfrDate();
        $headers = ['Cache-Control' => 'public, max-age=86400', 'ETag' => sprintf('"%s-boundaries"', $vfrDate)];
        if ($request->hasEtag($headers['ETag'])) {
            return Response::notModified($headers);
        }

        $file = $this->cache->remember($vfrDate, 'boundaries.geojson', $this->boundaries->featureCollection(...));

        return Response::file($file->path, 'application/geo+json', $headers + ['X-Cache' => $file->hit ? 'HIT' : 'MISS']);
    }
}
