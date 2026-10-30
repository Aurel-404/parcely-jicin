<?php

declare(strict_types=1);

namespace Parcely\Cli;

use Parcely\Repository\DataNotImported;
use Parcely\Repository\MetaRepository;
use Parcely\Tile\TileCache;

/**
 * Deletes cached tiles of previous data sets; keeps the one of the imported vfr_date unless $all.
 */
final readonly class ClearCacheCommand
{
    public function __construct(
        private TileCache $cache,
        private MetaRepository $meta,
        private Console $console,
    ) {}

    /**
     * @param bool $all delete the current data set too (after a change of the tile or boundary rendering)
     */
    public function run(bool $all = false): int
    {
        if ($all) {
            $deleted = $this->cache->clearExcept(null);
            $this->console->info(sprintf('Deleted the whole cache: %s', $deleted === [] ? '-' : implode(', ', $deleted)));

            return 0;
        }

        try {
            $current = $this->meta->vfrDate();
        } catch (DataNotImported $e) {
            $this->console->error($e->getMessage() . '; nothing to keep, nothing deleted (use --all to delete everything).');

            return 1;
        }

        $deleted = $this->cache->clearExcept($current);
        $this->console->info(sprintf(
            'Kept cache of data set %s, deleted %d older: %s',
            $current,
            count($deleted),
            $deleted === [] ? '-' : implode(', ', $deleted),
        ));

        return 0;
    }
}
