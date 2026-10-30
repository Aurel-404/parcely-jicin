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
        return $this->pdo ??= new PDO($this->dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
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
    private function execute(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
