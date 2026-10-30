<?php

declare(strict_types=1);

namespace Parcely\Vfr;

/**
 * Streams the VFR code list file ST_UCIS with XMLReader and returns the requested code lists.
 *
 * Structure (verified in 20261003_ST_UCIS.xml):
 *   <vf:DruhyPozemku>
 *     <vfc:DruhPozemku><vfc:Kod>2</vfc:Kod><vfc:Nazev>orná půda</vfc:Nazev>…</vfc:DruhPozemku>
 *     …
 *   </vf:DruhyPozemku>
 */
final class CodeListReader
{
    private const string NS_VF = 'urn:cz:isvs:ruian:schemas:SpecialniVymennyFormatTypy:v1';
    private const string NS_CODE_LIST = 'urn:cz:isvs:ruian:schemas:VFCiselnikyTypy:v1';

    /**
     * @param string $uri file path or stream URI, e.g. zip:///app/data/raw/x.xml.zip#x.xml
     * @param list<string> $names container elements, e.g. DruhyPozemku
     * @return array<string, array<int, string>> name => [code => name]
     */
    public function read(string $uri, array $names): array
    {
        $reader = new \XMLReader();
        if (!$reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException(sprintf('Cannot open %s', $uri));
        }

        $lists = [];
        try {
            // Items are in another namespace, so read() may walk through them; next() could skip an adjacent list.
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->namespaceURI === self::NS_VF
                    && in_array($reader->localName, $names, true)) {
                    $lists[$reader->localName] = self::items(Xml::expand($reader));
                }
            }
        } finally {
            $reader->close();
        }

        $missing = array_diff($names, array_keys($lists));
        if ($missing !== []) {
            throw new \RuntimeException(sprintf('%s has no code list %s', $uri, implode(', ', $missing)));
        }

        return $lists;
    }

    /**
     * @return array<int, string>
     */
    private static function items(\DOMElement $list): array
    {
        $items = [];
        foreach ($list->childNodes as $item) {
            if (!$item instanceof \DOMElement) {
                continue;
            }
            $code = Xml::intChild($item, self::NS_CODE_LIST, 'Kod');
            if (isset($items[$code])) {
                throw new \RuntimeException(sprintf('Code list %s contains code %d twice', $list->localName, $code));
            }
            $items[$code] = Xml::child($item, self::NS_CODE_LIST, 'Nazev')->textContent;
        }

        return $items;
    }
}
