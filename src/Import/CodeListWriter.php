<?php

declare(strict_types=1);

namespace Parcely\Import;

use Parcely\Database\Database;

/**
 * Stores code lists in tables cis_* (kod, nazev).
 *
 * All tables are written in one transaction with an upsert: a failed run changes nothing, and a code that
 * disappeared from ST_UCIS stays in the table, so already imported parcels never lose their description.
 */
final readonly class CodeListWriter
{
    public function __construct(private Database $db) {}

    /**
     * @param array<string, array<int, string>> $tables table => [code => name]
     */
    public function write(array $tables): void
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            foreach ($tables as $table => $items) {
                // Table names come from code, never from input; the check guards against a typo creating junk.
                if (preg_match('/^cis_[a-z_]+$/', $table) !== 1) {
                    throw new \InvalidArgumentException(sprintf('Invalid code list table name "%s"', $table));
                }
                $pdo->exec(sprintf('CREATE TABLE IF NOT EXISTS %s (kod smallint PRIMARY KEY, nazev text NOT NULL)', $table));
                $insert = $pdo->prepare(sprintf(
                    'INSERT INTO %s (kod, nazev) VALUES (:kod, :nazev) ON CONFLICT (kod) DO UPDATE SET nazev = EXCLUDED.nazev',
                    $table,
                ));
                foreach ($items as $code => $name) {
                    $insert->execute(['kod' => $code, 'nazev' => $name]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }
}
