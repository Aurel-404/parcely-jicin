<?php

declare(strict_types=1);

namespace Parcely\Vfr;

/**
 * Districts and their municipalities read from the state-wide VFR file (ST_UZSZ).
 */
final readonly class AdministrativeUnits
{
    /**
     * @param array<int, string> $districts district code => name
     * @param array<int, list<Municipality>> $municipalitiesByDistrict district code => municipalities
     */
    public function __construct(
        private array $districts,
        private array $municipalitiesByDistrict,
    ) {}

    public function districtCode(string $name): int
    {
        $codes = array_keys($this->districts, $name, true);
        if (count($codes) !== 1) {
            throw new \RuntimeException(sprintf('Expected exactly one district named "%s", found %d.', $name, count($codes)));
        }

        return $codes[0];
    }

    /**
     * @return list<Municipality> sorted by code
     */
    public function municipalitiesOf(int $districtCode): array
    {
        $municipalities = $this->municipalitiesByDistrict[$districtCode] ?? [];
        usort($municipalities, static fn(Municipality $a, Municipality $b): int => $a->code <=> $b->code);

        return $municipalities;
    }
}
