<?php

declare(strict_types=1);

namespace Parcely\Vfr;

use Parcely\Download\HttpClient;
use Parcely\Download\RemoteFile;

/**
 * Finds which VFR files are actually published, using HEAD requests (nothing is downloaded).
 */
final readonly class VfrLocator
{
    public function __construct(
        private HttpClient $http,
        private VfrSource $source,
    ) {}

    /**
     * Returns the first candidate date whose state boundary file exists; that date is then used for all files,
     * so the whole data set comes from one monthly copy.
     *
     * @param list<string> $candidates
     */
    public function findMonthlyDate(array $candidates): ?string
    {
        foreach ($candidates as $date) {
            if ($this->http->status($this->source->stateBoundaries($date)->url) === 200) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param list<string> $candidates
     */
    public function findCodeLists(array $candidates): ?RemoteFile
    {
        foreach ($candidates as $date) {
            $file = $this->source->codeLists($date);
            if ($this->http->status($file->url) === 200) {
                return $file;
            }
        }

        return null;
    }
}
