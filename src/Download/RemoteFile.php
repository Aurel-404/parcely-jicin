<?php

declare(strict_types=1);

namespace Parcely\Download;

/**
 * One downloadable archive: where it lives and the file name it is stored under locally.
 */
final readonly class RemoteFile
{
    public function __construct(
        public string $url,
        public string $name,
    ) {}

    public static function fromUrl(string $url): self
    {
        $path = parse_url($url, PHP_URL_PATH);
        $name = is_string($path) ? basename($path) : '';
        if (!str_ends_with($name, '.zip')) {
            throw new \InvalidArgumentException(sprintf('URL does not point to a .zip file: %s', $url));
        }

        return new self($url, $name);
    }
}
