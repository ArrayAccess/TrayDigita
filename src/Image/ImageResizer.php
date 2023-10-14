<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image;

use ArrayAccess\TrayDigita\Image\Factory\ImageResizerFactory;
use ArrayAccess\TrayDigita\Image\Interfaces\ImageResizerFactoryInterface;
use Psr\Http\Message\StreamInterface;

class ImageResizer
{
    protected ?ImageResizerFactoryInterface $imageResizerFactory = null;

    public function __construct(?ImageResizerFactoryInterface $imageResizerFactory = null)
    {
        if ($imageResizerFactory) {
            $this->setImageResizerFactory($imageResizerFactory);
        }
    }

    public function getImageResizerFactory(): ?ImageResizerFactoryInterface
    {
        return $this->imageResizerFactory ??= new ImageResizerFactory();
    }

    public function setImageResizerFactory(ImageResizerFactoryInterface $imageResizerFactory): void
    {
        $this->imageResizerFactory = $imageResizerFactory;
    }

    public function createFromFile(string $file): Interfaces\ImageAdapterInterface
    {
        return $this->getImageResizerFactory()->createFromFile($file);
    }

    public function createFromStream(StreamInterface $stream): Interfaces\ImageAdapterInterface
    {
        return $this->getImageResizerFactory()->createFromStream($stream);
    }
}
