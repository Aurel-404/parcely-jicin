<?php

declare(strict_types=1);

namespace Parcely\Cli;

use Parcely\Download\DownloadFailed;
use Parcely\Download\FileDownloader;
use Parcely\Download\RemoteFile;
use Parcely\Vfr\Municipality;
use Parcely\Vfr\MunicipalityCsv;
use Parcely\Vfr\VfrDate;
use Parcely\Vfr\VfrLocator;
use Parcely\Vfr\VfrSource;

/**
 * Downloads everything the import needs: OB_{code}_UKSH for every municipality in the CSV,
 * ST_UKSH (district boundary) and ST_UCIS (code lists). Re-running skips files that are already there.
 */
final readonly class DownloadCommand
{
    public function __construct(
        private VfrLocator $locator,
        private VfrSource $source,
        private FileDownloader $downloader,
        private Console $console,
    ) {}

    /**
     * @param string|null $date YYYYMMDD, null = newest published monthly copy
     * @param string|null $codeListUrl explicit ST_UCIS URL, null = find the newest one
     */
    public function run(\DateTimeImmutable $today, ?string $date, ?string $codeListUrl, string $csvPath): int
    {
        $started = hrtime(true);
        try {
            $files = $this->files($today, $date, $codeListUrl, $csvPath);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $this->console->error($e->getMessage());

            return 1;
        }

        $downloaded = $skipped = $bytes = 0;
        $errors = [];
        foreach ($files as $i => $file) {
            $progress = sprintf('[%3d/%d] %s', $i + 1, count($files), $file->name);
            try {
                $result = $this->downloader->fetch($file);
            } catch (DownloadFailed $e) {
                $errors[] = $e->getMessage();
                $this->console->error($progress . '  FAILED ' . $e->getMessage());
                continue;
            }

            if ($result->skipped) {
                $skipped++;
            } else {
                $downloaded++;
            }
            $bytes += $result->bytes;
            $this->console->info(sprintf('%s  %s %s', $progress, $result->skipped ? 'exists, skipped' : 'downloaded', Console::bytes($result->bytes)));
        }

        $this->console->info(sprintf(
            'Done in %s: %d files (%d downloaded, %d skipped, %d failed), %s in total.',
            Console::seconds($started),
            count($files),
            $downloaded,
            $skipped,
            count($errors),
            Console::bytes($bytes),
        ));

        return $errors === [] ? 0 : 1;
    }

    /**
     * @return list<RemoteFile>
     */
    private function files(\DateTimeImmutable $today, ?string $date, ?string $codeListUrl, string $csvPath): array
    {
        if ($date === null) {
            $candidates = VfrDate::monthlyCandidates($today);
            $date = $this->locator->findMonthlyDate($candidates)
                ?? throw new \RuntimeException(sprintf('No monthly VFR copy found for %s; pass --date=YYYYMMDD.', implode(', ', $candidates)));
            if ($date !== $candidates[0]) {
                $this->console->error(sprintf('Warning: %s is not published, using %s instead.', $candidates[0], $date));
            }
        }
        $this->console->info(sprintf('VFR data set: %s', $date));

        $codeLists = $codeListUrl !== null
            ? RemoteFile::fromUrl($codeListUrl)
            : $this->locator->findCodeLists(VfrDate::codeListCandidates($today))
                ?? throw new \RuntimeException('No ST_UCIS code list file found; pass --ucis-url=URL.');

        $files = array_map(
            fn(Municipality $municipality): RemoteFile => $this->source->municipality($date, $municipality->code),
            MunicipalityCsv::read($csvPath),
        );
        $files[] = $this->source->stateBoundaries($date);
        $files[] = $codeLists;

        return $files;
    }
}
