<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Domain;

use Parcely\Domain\CodeValue;
use Parcely\Domain\ParcelDetail;
use PHPUnit\Framework\TestCase;

final class ParcelDetailTest extends TestCase
{
    public function testJsonShapeWithLinksAndDataDate(): void
    {
        $detail = new ParcelDetail(
            id: 1755256604,
            label: 'st. 1868/26',
            cadastralArea: new CodeValue(659541, 'Jičín'),
            municipality: new CodeValue(572659, 'Jičín'),
            areaM2: 19,
            landType: new CodeValue(13, 'zastavěná plocha a nádvoří'),
            landUse: null,
            buildings: [],
            point: [15.371049, 50.430805],
            bbox: [15.371001, 50.430782, 15.371096, 50.430828],
            vfrDate: '20260930',
        );

        $json = $detail->jsonSerialize();

        self::assertSame(
            ['id', 'label', 'ku', 'obec', 'vymera_m2', 'druh_pozemku', 'zpusob_vyuziti', 'stavby', 'centroid', 'bbox', 'links', 'data_k'],
            array_keys($json),
        );
        self::assertSame(
            [
                'nahlizeni_kn' => 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1755256604',
                'vdp' => 'https://vdp.cuzk.gov.cz/vdp/ruian/parcely/1755256604',
            ],
            $json['links'],
        );
        self::assertSame('2026-09-30', $json['data_k']);
        self::assertNull($json['zpusob_vyuziti']);
    }
}
