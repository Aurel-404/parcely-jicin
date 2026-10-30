<?php

declare(strict_types=1);

namespace Parcely\Controller;

use Parcely\Domain\ParcelLabel;
use Parcely\Http\HttpException;
use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Repository\BoundaryRepository;
use Parcely\Repository\ParcelRepository;

final readonly class SearchController
{
    private const int LIMIT = 10;
    private const int MAX_LENGTH = 100;
    private const array CACHE = ['Cache-Control' => 'public, max-age=3600'];

    public function __construct(
        private ParcelRepository $parcels,
        private BoundaryRepository $boundaries,
    ) {}

    /**
     * GET /api/search?ku={name or code}&cislo={123/4 or st. 123/4}
     */
    public function parcels(Request $request): Response
    {
        $cadastralArea = self::text($request, 'ku');
        try {
            $label = ParcelLabel::parse(self::text($request, 'cislo'));
        } catch (\InvalidArgumentException $e) {
            throw HttpException::badRequest($e->getMessage());
        }

        return Response::json(['results' => $this->parcels->search($cadastralArea, $label, self::LIMIT)], 200, self::CACHE);
    }

    /**
     * GET /api/ku?q={part of the name}: suggestions for the search box.
     */
    public function cadastralAreas(Request $request): Response
    {
        $query = self::text($request, 'q');

        return Response::json(['results' => $this->boundaries->suggestCadastralAreas($query, self::LIMIT)], 200, self::CACHE);
    }

    /**
     * A required text parameter: trimmed, valid UTF-8 (PostgreSQL rejects anything else) and not too long.
     */
    private static function text(Request $request, string $name): string
    {
        $value = trim($request->query($name) ?? '');
        if ($value === '' || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > self::MAX_LENGTH) {
            throw HttpException::badRequest(sprintf('Parameter %s must be text of 1 to %d characters', $name, self::MAX_LENGTH));
        }

        return $value;
    }
}
