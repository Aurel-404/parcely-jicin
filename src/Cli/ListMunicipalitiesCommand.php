<?php

declare(strict_types=1);

namespace Parcely\Cli;

use Parcely\Download\FileDownloader;
use Parcely\Vfr\AdministrativeUnitsReader;
use Parcely\Vfr\MunicipalityCsv;
use Parcely\Vfr\VfrDate;
use Parcely\Vfr\VfrLocator;
use Parcely\Vfr\VfrSource;
use Parcely\Vfr\Xml;

/**
 * Downloads the state-wide basic VFR file (ST_UZSZ) and writes the municipalities of one district to CSV.
 * The CSV is committed: it documents the scope and is the input of the download command.
 */
final readonly class ListMunicipalitiesCommand
{
    public function __construct(
        private VfrLocator $locator,
        private VfrSource $source,
        private FileDownloader $downloader,
        private AdministrativeUnitsReader $reader,
        private Console $console,
    ) {}

    /**
     * @param string|null $date YYYYMMDD, null = newest published monthly copy
     * @param int $expectedCount safety check against an official count (ČSÚ)
     */
    public function run(\DateTimeImmutable $today, ?string $date, string $districtName, int $expectedCount, string $csvPath): int
    {
        try {
            $date ??= $this->locator->findMonthlyDate(VfrDate::monthlyCandidates($today))
                ?? throw new \RuntimeException('No published monthly VFR copy found; pass --date=YYYYMMDD.');

            $file = $this->source->stateBasic($date);
            $this->console->info(sprintf('Reading %s …', $file->name));
            $units = $this->reader->read(Xml::zipEntryUri($this->downloader->fetch($file)->path));

            $districtCode = $units->districtCode($districtName);
            $municipalities = $units->municipalitiesOf($districtCode);
            $this->console->info(sprintf('District %s (code %d): %d municipalities', $districtName, $districtCode, count($municipalities)));

            if (count($municipalities) !== $expectedCount) {
                throw new \RuntimeException(sprintf('Expected %d municipalities, found %d - CSV not written.', $expectedCount, count($municipalities)));
            }

            MunicipalityCsv::write($csvPath, $municipalities);
            $this->console->info(sprintf('Written %s', $csvPath));

            return 0;
        } catch (\RuntimeException $e) {
            $this->console->error($e->getMessage());

            return 1;
        }
    }
}
