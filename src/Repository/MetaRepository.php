<?php

declare(strict_types=1);

namespace Parcely\Repository;

use Parcely\Database\Database;

/**
 * Import metadata in table meta (written by db/transform.sql).
 */
final readonly class MetaRepository
{
    public function __construct(private Database $db) {}

    /**
     * Date of the imported VFR data set (YYYYMMDD); it versions the tile cache.
     *
     * @throws DataNotImported
     */
    public function vfrDate(): string
    {
        try {
            $date = $this->db->fetchValue("SELECT value FROM meta WHERE key = 'vfr_date'");
        } catch (\PDOException $e) {
            // 42P01 = undefined_table: the import has never run.
            throw $e->getCode() === '42P01' ? new DataNotImported() : $e;
        }

        return is_string($date) ? $date : throw new DataNotImported();
    }
}
