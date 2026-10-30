<?php

declare(strict_types=1);

namespace Parcely\Vfr;

/**
 * Helpers for VFR elements expanded from XMLReader: every value is a namespaced direct child element.
 */
final class Xml
{
    public static function expand(\XMLReader $reader): \DOMElement
    {
        $node = $reader->expand();
        if (!$node instanceof \DOMElement) {
            throw new \RuntimeException(sprintf('Cannot read element %s', $reader->name));
        }

        return $node;
    }

    public static function child(\DOMElement $parent, string $namespace, string $name): \DOMElement
    {
        return self::optionalChild($parent, $namespace, $name)
            ?? throw new \RuntimeException(sprintf('Element %s has no child %s', $parent->nodeName, $name));
    }

    public static function optionalChild(\DOMElement $parent, string $namespace, string $name): ?\DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->namespaceURI === $namespace && $node->localName === $name) {
                return $node;
            }
        }

        return null;
    }

    public static function intChild(\DOMElement $parent, string $namespace, string $name): int
    {
        $value = self::child($parent, $namespace, $name)->textContent;
        if (!ctype_digit($value)) {
            throw new \RuntimeException(sprintf('Element %s/%s is not a number: "%s"', $parent->nodeName, $name, $value));
        }

        return (int) $value;
    }

    /**
     * Each VFR archive contains exactly one XML file; returns a URI that reads it straight from the ZIP.
     */
    public static function zipEntryUri(string $zipPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true || $zip->count() !== 1) {
            throw new \RuntimeException(sprintf('%s: expected a ZIP with one XML file', $zipPath));
        }
        $entry = (string) $zip->getNameIndex(0);
        $zip->close();

        return sprintf('zip://%s#%s', $zipPath, $entry);
    }
}
