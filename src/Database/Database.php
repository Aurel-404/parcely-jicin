<?php

declare(strict_types=1);

namespace Parcely\Database;

use Parcely\Config;
use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper: connects lazily (only requests that need the database pay for the connection)
 * and always uses prepared statements.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        #[\SensitiveParameter]
        private readonly string $password,
    ) {}

    public static function fromConfig(Config $config): self
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;connect_timeout=3;application_name=parcely',
            $config->dbHost,
            $config->dbPort,
            $config->dbName,
        );

        return new self($dsn, $config->dbUser, $config->dbPassword);
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
            try {
                // A kept connection may be dead (the database restarted). The failed query marks it as bad,
                // so connecting again opens a new connection instead of returning the same dead one.
                $this->pdo->query('SELECT 1');
            } catch (\PDOException) {
                $this->pdo = $this->connect();
            }
        }

        return $this->pdo;
    }

    /**
     * Returns the first column of the first row, or null when there is no row.
     *
     * @param array<string, scalar|null> $params
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->execute($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function fetchRow(string $sql, array $params = []): ?Row
    {
        $row = $this->execute($sql, $params)->fetch();

        return is_array($row) ? new Row($row) : null;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return list<Row>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $rows = [];
        foreach ($this->execute($sql, $params)->fetchAll() as $row) {
            // FETCH_ASSOC (the default fetch mode) always gives arrays.
            $rows[] = new Row(is_array($row) ? $row : throw new \UnexpectedValueException('Row is not an array'));
        }

        return $rows;
    }

    /**
     * Persistent: every PHP-FPM worker keeps its connection, so a request skips the connection setup and the
     * PostgreSQL backend keeps PostGIS and PROJ loaded (measured: parcel detail 40 ms → 9 ms).
     */
    private function connect(): PDO
    {
        return new PDO($this->dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
        ]);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function execute(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
