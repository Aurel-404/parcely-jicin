<?php

declare(strict_types=1);

namespace Parcely\Controller;

use Parcely\Http\HttpException;
use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Repository\ParcelRepository;

final readonly class ParcelController
{
    /** The data changes once a month; an hour in the browser cache is harmless. */
    private const array CACHE = ['Cache-Control' => 'public, max-age=3600'];

    public function __construct(private ParcelRepository $parcels) {}

    /**
     * GET /api/parcels/{id}
     *
     * @param array<string, string> $params
     */
    public function detail(Request $request, array $params): Response
    {
        $id = $params['id'];
        // Positive, without leading zeros, and small enough for a PHP int (RÚIAN ids have up to 11 digits).
        if (preg_match('/^[1-9][0-9]{0,17}$/', $id) !== 1) {
            throw HttpException::badRequest('Parcel id must be a positive integer');
        }

        $parcel = $this->parcels->find((int) $id)
            ?? throw HttpException::notFound(sprintf('Parcel %s not found', $id));

        return Response::json($parcel, 200, self::CACHE);
    }

    /**
     * GET /api/druhy-pozemku: kinds of land in the district (code list names) with parcel counts, for the legend.
     */
    public function landTypes(): Response
    {
        return Response::json(['results' => $this->parcels->countByLandType()], 200, self::CACHE);
    }
}
