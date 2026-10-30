<?php

declare(strict_types=1);

namespace Parcely\Controller;

use Parcely\Database\Database;
use Parcely\Http\Response;

final readonly class HealthController
{
    private const array NO_CACHE = ['Cache-Control' => 'no-store'];

    public function __construct(private Database $db) {}

    public function check(): Response
    {
        try {
            $postgis = $this->db->fetchValue('SELECT postgis_lib_version()');
        } catch (\PDOException $e) {
            error_log('Health check failed: ' . $e->getMessage());

            return Response::json(['status' => 'error', 'database' => 'unavailable'], 503, self::NO_CACHE);
        }

        return Response::json(['status' => 'ok', 'database' => 'ok', 'postgis' => $postgis], 200, self::NO_CACHE);
    }
}
