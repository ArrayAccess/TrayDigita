<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Interfaces;

use Psr\Http\Message\StreamInterface;

interface ImageResizerFactoryInterface
{
    /**
     * Create an image adapter instance from a file.
     *
     * @param string $file The file path.
     * @return ImageAdapterInterface
     */
    public function createFromFile(string $file) : ImageAdapterInterface;

    /**
     * Create an image adapter instance from a stream.
     *
     * @param StreamInterface $stream The stream that contain image data.
     * @return ImageAdapterInterface
     */
    public function createFromStream(StreamInterface $stream) : ImageAdapterInterface;
}
