<?php

declare(strict_types=1);

namespace Parcely\Tests\Unit\Vfr;

use Parcely\Vfr\CodeListReader;
use PHPUnit\Framework\TestCase;

final class CodeListReaderTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../Fixtures/st_ucis_excerpt.xml';

    public function testReadsRequestedCodeLists(): void
    {
        // DruhyCislovaniParcel and DruhyPozemku are adjacent in the file, as in the real ST_UCIS.
        $lists = (new CodeListReader())->read(self::FIXTURE, ['DruhyCislovaniParcel', 'DruhyPozemku']);

        self::assertSame(
            [
                'DruhyCislovaniParcel' => [1 => 'Stavební', 2 => 'Pozemková'],
                'DruhyPozemku' => [2 => 'orná půda', 3 => 'chmelnice', 4 => 'vinice'],
            ],
            $lists,
        );
    }

    public function testIgnoresOtherCodeLists(): void
    {
        $lists = (new CodeListReader())->read(self::FIXTURE, ['TypyStavebnichObjektu']);

        self::assertSame(['TypyStavebnichObjektu'], array_keys($lists));
        self::assertSame('budova bez č.p. i č.ev.', $lists['TypyStavebnichObjektu'][3]);
    }

    public function testMissingCodeListFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no code list ZpusobyVyuzitiPozemku');

        (new CodeListReader())->read(self::FIXTURE, ['DruhyPozemku', 'ZpusobyVyuzitiPozemku']);
    }
}
