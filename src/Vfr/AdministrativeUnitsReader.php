<?php

declare(strict_types=1);

namespace Parcely\Vfr;

/**
 * Streams a VFR XML file with XMLReader (the state file has ~50 MB) and collects districts and municipalities.
 *
 * Structure (verified in 20260930_ST_UZSZ.xml):
 *   <vf:Okres><oki:Kod>3604</oki:Kod><oki:Nazev>Jičín</oki:Nazev>…</vf:Okres>
 *   <vf:Obec><obi:Kod>…</obi:Kod><obi:Nazev>…</obi:Nazev><obi:Okres><oki:Kod>3604</oki:Kod></obi:Okres>…</vf:Obec>
 */
final class AdministrativeUnitsReader
{
    private const string NS_VF = 'urn:cz:isvs:ruian:schemas:VymennyFormatTypy:v1';
    private const string NS_DISTRICT = 'urn:cz:isvs:ruian:schemas:OkresIntTypy:v1';
    private const string NS_MUNICIPALITY = 'urn:cz:isvs:ruian:schemas:ObecIntTypy:v1';

    /**
     * @param string $uri file path or stream URI, e.g. zip:///app/data/raw/x.xml.zip#x.xml
     */
    public function read(string $uri): AdministrativeUnits
    {
        $reader = new \XMLReader();
        if (!$reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException(sprintf('Cannot open %s', $uri));
        }

        $districts = [];
        $municipalities = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->namespaceURI !== self::NS_VF) {
                    continue;
                }
                if ($reader->localName === 'Okres') {
                    $element = self::expand($reader);
                    $districts[self::intChild($element, self::NS_DISTRICT, 'Kod')] = self::child($element, self::NS_DISTRICT, 'Nazev')->textContent;
                } elseif ($reader->localName === 'Obec') {
                    $element = self::expand($reader);
                    // Praha (554782) belongs to no district, so it has no <obi:Okres>.
                    $district = self::optionalChild($element, self::NS_MUNICIPALITY, 'Okres');
                    if ($district === null) {
                        continue;
                    }
                    $municipalities[self::intChild($district, self::NS_DISTRICT, 'Kod')][] = new Municipality(
                        self::intChild($element, self::NS_MUNICIPALITY, 'Kod'),
                        self::child($element, self::NS_MUNICIPALITY, 'Nazev')->textContent,
                    );
                }
            }
        } finally {
            $reader->close();
        }

        return new AdministrativeUnits($districts, $municipalities);
    }

    private static function expand(\XMLReader $reader): \DOMElement
    {
        $node = $reader->expand();
        if (!$node instanceof \DOMElement) {
            throw new \RuntimeException(sprintf('Cannot read element %s', $reader->name));
        }

        return $node;
    }

    private static function child(\DOMElement $parent, string $namespace, string $name): \DOMElement
    {
        return self::optionalChild($parent, $namespace, $name)
            ?? throw new \RuntimeException(sprintf('Element %s has no child %s', $parent->nodeName, $name));
    }

    private static function optionalChild(\DOMElement $parent, string $namespace, string $name): ?\DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->namespaceURI === $namespace && $node->localName === $name) {
                return $node;
            }
        }

        return null;
    }

    private static function intChild(\DOMElement $parent, string $namespace, string $name): int
    {
        $value = self::child($parent, $namespace, $name)->textContent;
        if (!ctype_digit($value)) {
            throw new \RuntimeException(sprintf('Element %s/%s is not a number: "%s"', $parent->nodeName, $name, $value));
        }

        return (int) $value;
    }
}
