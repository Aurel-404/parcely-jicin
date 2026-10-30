<?php

declare(strict_types=1);

namespace Parcely\Cli;

use Parcely\Repository\DataNotImported;
use Parcely\Repository\MetaRepository;
use Parcely\Tile\TileCache;

/**
 * Deletes cached tiles of previous data sets; keeps the one of the imported vfr_date.
 */
final readonly class ClearCacheCommand
{
    public function __construct(
        private TileCache $cache,
        private MetaRepository $meta,
        private Console $console,
    ) {}

    public function run(): int
    {
        try {
            $current = $this->meta->vfrDate();
        } catch (DataNotImported $e) {
            $this->console->error($e->getMessage() . '; nothing to keep, nothing deleted.');

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
