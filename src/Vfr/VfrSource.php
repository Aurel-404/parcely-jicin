<?php

declare(strict_types=1);

namespace Parcely\Vfr;

use Parcely\Download\RemoteFile;

/**
 * Builds URLs of RÚIAN exchange format (VFR) files published by ČÚZK.
 *
 * Verified on 2026-10-30: monthly full copies are in /soucasna/, code lists (ST_UCIS) only in /specialni/.
 * File name: {date}_{OB_<municipality code>|ST}_{data set}.xml.zip
 */
final readonly class VfrSource
{
    public const string DEFAULT_BASE_URL = 'https://vdp.cuzk.gov.cz/vymenny_format';

    private string $baseUrl;

    public function __construct(string $baseUrl = self::DEFAULT_BASE_URL)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * UKSH = complete current data set with original boundaries: parcels with polygons, cadastral areas,
     * the municipality itself and buildings.
     */
    public function municipality(string $date, int $municipalityCode): RemoteFile
    {
        return $this->file('soucasna', sprintf('%s_OB_%d_UKSH.xml.zip', $date, $municipalityCode));
    }

    /**
     * UZSZ = basic current data set for the whole state down to ZSJ, without boundaries.
     * Used for the list of municipalities and their districts.
     */
    public function stateBasic(string $date): RemoteFile
    {
        return $this->file('soucasna', sprintf('%s_ST_UZSZ.xml.zip', $date));
    }

    /**
     * UKSH for the state = units down to POU (incl. districts) with original boundaries.
     */
    public function stateBoundaries(string $date): RemoteFile
    {
        return $this->file('soucasna', sprintf('%s_ST_UKSH.xml.zip', $date));
    }

    /**
     * UCIS = code lists (land type, land use, numbering type ...). Dated differently from the monthly sets.
     */
    public function codeLists(string $date): RemoteFile
    {
        return $this->file('specialni', sprintf('%s_ST_UCIS.xml.zip', $date));
    }

    private function file(string $directory, string $name): RemoteFile
    {
        return new RemoteFile(sprintf('%s/%s/%s', $this->baseUrl, $directory, $name), $name);
    }
}
