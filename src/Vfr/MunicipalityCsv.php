<?php

declare(strict_types=1);

namespace Parcely\Vfr;

/**
 * The committed list of municipalities in scope (data/obce_okres_jicin.csv): "kod;nazev", UTF-8.
 */
final class MunicipalityCsv
{
    private const array HEADER = ['kod', 'nazev'];
    private const string SEPARATOR = ';';

    /**
     * @param list<Municipality> $municipalities
     */
    public static function write(string $path, array $municipalities): void
    {
        $file = self::open($path, 'wb');
        try {
            fputcsv($file, self::HEADER, self::SEPARATOR, escape: '');
            foreach ($municipalities as $municipality) {
                fputcsv($file, [$municipality->code, $municipality->name], self::SEPARATOR, escape: '');
            }
        } finally {
            fclose($file);
        }
    }

    /**
     * @return list<Municipality>
     */
    public static function read(string $path): array
    {
        $file = self::open($path, 'rb');
        try {
            if (fgetcsv($file, separator: self::SEPARATOR, escape: '') !== self::HEADER) {
                throw new \RuntimeException(sprintf('%s: expected header "%s"', $path, implode(self::SEPARATOR, self::HEADER)));
            }

            $municipalities = [];
            $line = 1;
            while (($row = fgetcsv($file, separator: self::SEPARATOR, escape: '')) !== false) {
                $line++;
                if (count($row) !== 2 || !ctype_digit((string) $row[0]) || (string) $row[1] === '') {
                    throw new \RuntimeException(sprintf('%s:%d: expected "kod;nazev"', $path, $line));
                }
                $municipalities[] = new Municipality((int) $row[0], (string) $row[1]);
            }

            return $municipalities;
        } finally {
            fclose($file);
        }
    }

    /**
     * @return resource
     */
    private static function open(string $path, string $mode)
    {
        $file = fopen($path, $mode);
        if ($file === false) {
            throw new \RuntimeException(sprintf('Cannot open %s', $path));
        }

        return $file;
    }
}
