<?php

declare(strict_types=1);

namespace Parcely\Tile;

/**
 * Disk cache of rendered tiles and other derived files, versioned by the VFR data set:
 * {directory}/{vfr_date}/parcels/14/8892/5526.pbf, {directory}/{vfr_date}/boundaries.geojson.
 *
 * A new import has a new vfr_date and therefore an empty folder, so a stale file is never served and the
 * import does not have to delete anything. Old versions are removed by bin/clear-cache.php.
 */
final readonly class TileCache
{
    public function __construct(private string $directory) {}

    /**
     * Returns the cached file, rendering and storing it first on a miss.
     *
     * @param string $name relative path, e.g. "parcels/14/8892/5526.pbf"
     * @param \Closure(): string $render
     */
    public function remember(string $vfrDate, string $name, \Closure $render): CachedFile
    {
        if (preg_match('#^[a-z]+(/\d+)*(/\d+\.pbf|\.geojson)$#', $name) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid cache file name "%s"', $name));
        }
        $path = sprintf('%s/%s/%s', $this->directory, self::version($vfrDate), $name);

        if (is_file($path)) {
            return new CachedFile($path, true);
        }
        self::write($path, $render());

        return new CachedFile($path, false);
    }

    /**
     * Deletes all versions except $vfrDate (null = all, after a change of the rendering code); returns the
     * deleted ones.
     *
     * @return list<string>
     */
    public function clearExcept(?string $vfrDate): array
    {
        $keep = $vfrDate === null ? null : self::version($vfrDate);
        $deleted = [];
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $version = basename($folder);
            // Only data set folders; anything else in the directory is not ours to delete.
            if ($version !== $keep && preg_match('/^\d{8}$/', $version) === 1) {
                self::deleteTree($folder);
                $deleted[] = $version;
            }
        }
        sort($deleted);

        return $deleted;
    }

    private static function version(string $vfrDate): string
    {
        if (preg_match('/^\d{8}$/', $vfrDate) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid VFR date "%s"', $vfrDate));
        }

        return $vfrDate;
    }

    /**
     * Atomic: writes a temporary file next to the target and renames it, so a concurrent request never reads
     * a half-written tile. Two requests rendering the same tile both succeed; the last rename wins.
     */
    private static function write(string $path, string $content): void
    {
        $folder = dirname($path);
        if (!is_dir($folder) && !mkdir($folder, 0o775, true) && !is_dir($folder)) {
            throw new \RuntimeException(sprintf('Cannot create cache folder %s', $folder));
        }
        $temporary = sprintf('%s.%s.tmp', $path, bin2hex(random_bytes(6)));
        if (file_put_contents($temporary, $content) !== strlen($content) || !rename($temporary, $path)) {
            @unlink($temporary);

            throw new \RuntimeException(sprintf('Cannot write cache file %s', $path));
        }
    }

    private static function deleteTree(string $folder): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($folder);
    }
}
