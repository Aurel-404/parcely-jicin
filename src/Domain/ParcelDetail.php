<?php

declare(strict_types=1);

namespace Parcely\Domain;

/**
 * Everything the detail panel shows after a click on a parcel.
 */
final readonly class ParcelDetail implements \JsonSerializable
{
    /** Verified: opens the parcel in Nahlížení do katastru nemovitostí (owners are there, behind a captcha). */
    private const string NAHLIZENI_KN_URL = 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=%d';
    /** Verified: parcel detail in the RÚIAN public application (VDP). */
    private const string VDP_URL = 'https://vdp.cuzk.gov.cz/vdp/ruian/parcely/%d';

    /**
     * @param list<Building> $buildings
     * @param array{float, float} $point lon, lat (WGS 84), always inside the parcel
     * @param array{float, float, float, float} $bbox minLon, minLat, maxLon, maxLat (WGS 84)
     * @param string $vfrDate YYYYMMDD, date of the VFR data set
     */
    public function __construct(
        public int $id,
        public string $label,
        public CodeValue $cadastralArea,
        public CodeValue $municipality,
        public int $areaM2,
        public ?CodeValue $landType,
        public ?CodeValue $landUse,
        public array $buildings,
        public array $point,
        public array $bbox,
        public string $vfrDate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'ku' => $this->cadastralArea,
            'obec' => $this->municipality,
            'vymera_m2' => $this->areaM2,
            'druh_pozemku' => $this->landType,
            'zpusob_vyuziti' => $this->landUse,
            'stavby' => $this->buildings,
            'centroid' => $this->point,
            'bbox' => $this->bbox,
            'links' => [
                'nahlizeni_kn' => sprintf(self::NAHLIZENI_KN_URL, $this->id),
                'vdp' => sprintf(self::VDP_URL, $this->id),
            ],
            'data_k' => sprintf('%s-%s-%s', substr($this->vfrDate, 0, 4), substr($this->vfrDate, 4, 2), substr($this->vfrDate, 6, 2)),
        ];
    }
}
