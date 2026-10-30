<?php

declare(strict_types=1);

namespace Parcely\Cli;

use Parcely\Import\CodeListWriter;
use Parcely\Vfr\CodeListReader;
use Parcely\Vfr\Xml;

/**
 * Loads the code lists the application shows (parcel and building descriptions) from ST_UCIS into tables cis_*.
 * Codes are never typed in by hand; db/transform.sql checks the one it depends on.
 */
final readonly class ImportCodeListsCommand
{
    /** ST_UCIS code list => table */
    public const array TABLES = [
        'DruhyCislovaniParcel' => 'cis_druh_cislovani',
        'DruhyPozemku' => 'cis_druh_pozemku',
        'ZpusobyVyuzitiPozemku' => 'cis_zpusob_vyuziti',
        'TypyStavebnichObjektu' => 'cis_typ_stavebniho_objektu',
        'ZpusobyVyuzitiObjektu' => 'cis_zpusob_vyuziti_objektu',
    ];

    public function __construct(
        private CodeListReader $reader,
        private CodeListWriter $writer,
        private Console $console,
    ) {}

    /**
     * @param string|null $file ST_UCIS archive, null = the newest one in $rawDirectory
     */
    public function run(string $rawDirectory, ?string $file): int
    {
        try {
            $file ??= self::newestCodeListFile($rawDirectory);
            $this->console->info(sprintf('Reading %s …', basename($file)));
            $lists = $this->reader->read(Xml::zipEntryUri($file), array_keys(self::TABLES));

            $tables = [];
            foreach (self::TABLES as $list => $table) {
                $tables[$table] = $lists[$list];
            }
            $this->writer->write($tables);

            foreach ($tables as $table => $items) {
                $this->console->info(sprintf('  %-28s %3d codes', $table, count($items)));
            }

            return 0;
        } catch (\RuntimeException $e) {
            $this->console->error($e->getMessage());

            return 1;
        }
    }

    private static function newestCodeListFile(string $rawDirectory): string
    {
        // File names start with the date (YYYYMMDD), so the last one in alphabetical order is the newest.
        $files = glob($rawDirectory . '/*_ST_UCIS.xml.zip') ?: [];
        sort($files);

        return end($files)
            ?: throw new \RuntimeException(sprintf('No *_ST_UCIS.xml.zip in %s; run bin/download.php first.', $rawDirectory));
    }
}
