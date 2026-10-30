<?php

declare(strict_types=1);

namespace Parcely\Repository;

/**
 * The database has no imported VFR data yet (bin/import.sh has not run); the application answers 503.
 */
final class DataNotImported extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Data not imported yet');
    }
}
