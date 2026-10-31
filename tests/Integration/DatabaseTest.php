<?php

declare(strict_types=1);

namespace Parcely\Tests\Integration;

final class DatabaseTest extends IntegrationTestCase
{
    public function testParametersKeepTheirTypes(): void
    {
        $row = self::database()->fetchRow(
            'SELECT :yes::boolean AS yes, :no::boolean AS no, :number + 1 AS number, :nothing::text IS NULL AS nothing, :text AS text',
            ['yes' => true, 'no' => false, 'number' => 41, 'nothing' => null, 'text' => 'Jičín'],
        );

        self::assertNotNull($row);
        self::assertSame(
            ['yes' => true, 'no' => false, 'number' => 42, 'nothing' => true, 'text' => 'Jičín'],
            [
                'yes' => $row->bool('yes'),
                'no' => $row->bool('no'),
                'number' => $row->int('number'),
                'nothing' => $row->bool('nothing'),
                'text' => $row->string('text'),
            ],
        );
    }
}
