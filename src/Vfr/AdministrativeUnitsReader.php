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
                    $element = Xml::expand($reader);
                    $districts[Xml::intChild($element, self::NS_DISTRICT, 'Kod')] = Xml::child($element, self::NS_DISTRICT, 'Nazev')->textContent;
                } elseif ($reader->localName === 'Obec') {
                    $element = Xml::expand($reader);
                    // Praha (554782) belongs to no district, so it has no <obi:Okres>.
                    $district = Xml::optionalChild($element, self::NS_MUNICIPALITY, 'Okres');
                    if ($district === null) {
                        continue;
                    }
                    $municipalities[Xml::intChild($district, self::NS_DISTRICT, 'Kod')][] = new Municipality(
                        Xml::intChild($element, self::NS_MUNICIPALITY, 'Kod'),
                        Xml::child($element, self::NS_MUNICIPALITY, 'Nazev')->textContent,
                    );
                }
            }
        } finally {
            $reader->close();
        }

        return new AdministrativeUnits($districts, $municipalities);
    }
}
