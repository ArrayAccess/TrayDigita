<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image;

use ArrayAccess\TrayDigita\Http\Stream;
use ArrayAccess\TrayDigita\Image\Factory\ImageResizerFactory;
use ArrayAccess\TrayDigita\Image\Interfaces\ImageResizerFactoryInterface;
use Psr\Http\Message\StreamInterface;

class ImageResizer
{
    /**
     * The image resizer factory instance.
     *
     * @var ImageResizerFactoryInterface|null $imageResizerFactory
     */
    protected ?ImageResizerFactoryInterface $imageResizerFactory = null;

    /**
     * ImageResizer constructor.
     *
     * @param ImageResizerFactoryInterface|null $imageResizerFactory
     */
    public function __construct(?ImageResizerFactoryInterface $imageResizerFactory = null)
    {
        if ($imageResizerFactory) {
            $this->setImageResizerFactory($imageResizerFactory);
        }
    }

    /**
     * Get the image resizer factory instance.
     *
     * @return ImageResizerFactoryInterface
     */
    public function getImageResizerFactory(): ImageResizerFactoryInterface
    {
        return $this->imageResizerFactory ??= new ImageResizerFactory();
    }

    /**
     * Set the image resizer factory instance.
     *
     * @param ImageResizerFactoryInterface $imageResizerFactory
     * @return void
     */
    public function setImageResizerFactory(ImageResizerFactoryInterface $imageResizerFactory): void
    {
        $this->imageResizerFactory = $imageResizerFactory;
    }

    /**
     * Create an image resizer instance from a file.
     *
     * @param string $file
     * @return Interfaces\ImageAdapterInterface
     */
    public function createFromFile(string $file): Interfaces\ImageAdapterInterface
    {
        return $this->getImageResizerFactory()->createFromFile($file);
    }

    /**
     * Create an image resizer instance from a stream.
     *
     * @param StreamInterface $stream
     * @return Interfaces\ImageAdapterInterface
     */
    public function createFromStream(StreamInterface $stream): Interfaces\ImageAdapterInterface
    {
        return $this->getImageResizerFactory()->createFromStream($stream);
    }

    /**
     * Create an image resizer instance from a string.
     *
     * @param string $imageString
     * @return Interfaces\ImageAdapterInterface
     */
    public function createImageFromString(string $imageString): Interfaces\ImageAdapterInterface
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        while (strlen($imageString) > 0) {
            $stream->write(substr($imageString, 0, 8192));
            $imageString = substr($imageString, 8192);
        }
        $imageString = null;
        unset($imageString);
        $stream->rewind();
        return $this->createFromStream($stream);
    }
}
