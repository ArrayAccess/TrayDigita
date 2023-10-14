<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Interfaces;

use Psr\Http\Message\StreamInterface;

interface ImageResizerFactoryInterface
{
    /**
     * @param string $file
     * @return ImageAdapterInterface
     */
    public function createFromFile(string $file) : ImageAdapterInterface;

    /**
     * @param StreamInterface $stream
     * @return ImageAdapterInterface
     */
    public function createFromStream(StreamInterface $stream) : ImageAdapterInterface;
}
