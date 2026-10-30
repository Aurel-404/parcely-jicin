<?php

declare(strict_types=1);

namespace Parcely\Database;

/**
 * One result row with typed access. pdo_pgsql returns integer and boolean columns as PHP int and bool,
 * but float8 and numeric as strings; a column of another type than expected is a bug in the query.
 */
final readonly class Row
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(private array $values) {}

    public function int(string $column): int
    {
        return $this->nullableInt($column) ?? throw self::unexpected($column, 'int');
    }

    public function nullableInt(string $column): ?int
    {
        $value = $this->value($column);
        if ($value !== null && !is_int($value)) {
            throw self::unexpected($column, 'int');
        }

        return $value;
    }

    public function string(string $column): string
    {
        return $this->nullableString($column) ?? throw self::unexpected($column, 'string');
    }

    public function nullableString(string $column): ?string
    {
        $value = $this->value($column);
        if ($value !== null && !is_string($value)) {
            throw self::unexpected($column, 'string');
        }

        return $value;
    }

    public function float(string $column): float
    {
        $value = $this->value($column);
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (float) $value;
        }

        throw self::unexpected($column, 'float');
    }

    /**
     * A JSON column (e.g. array_to_json(…)) decoded to a list of integers.
     *
     * @return list<int>
     */
    public function intList(string $column): array
    {
        $value = $this->value($column);
        $list = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : null;
        if (!is_array($list) || !array_is_list($list) || array_filter($list, is_int(...)) !== $list) {
            throw self::unexpected($column, 'JSON list of int');
        }

        /** @var list<int> */
        return $list;
    }

    private function value(string $column): mixed
    {
        if (!array_key_exists($column, $this->values)) {
            throw new \UnexpectedValueException(sprintf('Result has no column "%s"', $column));
        }

        return $this->values[$column];
    }

    private static function unexpected(string $column, string $type): \UnexpectedValueException
    {
        return new \UnexpectedValueException(sprintf('Column "%s" is not %s', $column, $type));
    }
}
