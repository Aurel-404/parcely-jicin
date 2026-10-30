<?php

declare(strict_types=1);

namespace Parcely\Tests\Integration;

use Parcely\Database\Database;
use Parcely\Import\CodeListWriter;

/**
 * Writes to a throw-away table in the real database.
 */
final class CodeListWriterTest extends IntegrationTestCase
{
    private const string TABLE = 'cis_phpunit_test';

    private Database $db;

    protected function setUp(): void
    {
        $this->db = self::database();
        $this->db->pdo()->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->pdo()->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        }
    }

    public function testUpsertKeepsCodesMissingFromNewerFile(): void
    {
        $writer = new CodeListWriter($this->db);

        $writer->write([self::TABLE => [1 => 'Stavební', 2 => 'pozemková']]);
        $writer->write([self::TABLE => [2 => 'Pozemková', 3 => 'nová']]);

        self::assertSame([1 => 'Stavební', 2 => 'Pozemková', 3 => 'nová'], $this->rows());
    }

    public function testFailureRollsBackAllTables(): void
    {
        try {
            (new CodeListWriter($this->db))->write([self::TABLE => [1 => 'Stavební'], 'cis_bad name' => []]);
            self::fail('Invalid table name was accepted.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('cis_bad name', $e->getMessage());
        }

        self::assertNull($this->db->fetchValue('SELECT to_regclass(:table)', ['table' => self::TABLE]), 'CREATE TABLE was not rolled back');
    }

    /**
     * @return array<int, string>
     */
    private function rows(): array
    {
        $statement = $this->db->pdo()->query('SELECT kod, nazev FROM ' . self::TABLE . ' ORDER BY kod');
        self::assertNotFalse($statement);

        /** @var array<int, string> */
        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
