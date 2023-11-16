<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Factory;

use ArrayAccess\TrayDigita\Image\Adapter\Gd;
use ArrayAccess\TrayDigita\Image\Adapter\Imagick;
use ArrayAccess\TrayDigita\Image\Exceptions\ImageFileNotFoundException;
use ArrayAccess\TrayDigita\Image\Exceptions\UnsupportedAdapter;
use ArrayAccess\TrayDigita\Image\Interfaces\ImageAdapterInterface;
use ArrayAccess\TrayDigita\Image\Interfaces\ImageResizerFactoryInterface;
use Psr\Http\Message\StreamInterface;
use function is_file;

class ImageResizerFactory implements ImageResizerFactoryInterface
{
    public const USE_GD = 1;

    public const USE_IMAGICK = 2;

    /**
     * @var int|false|null USE_GD|USE_IMAGICK
     */
    private static int|null|false $imageGenerationMode = null;

    private static bool $GdExists = false;

    private static bool $ImagickExists = false;

    public function __construct()
    {
        if (self::$imageGenerationMode === null) {
            self::$GdExists            = extension_loaded('gd');
            self::$ImagickExists       = extension_loaded('imagick');
            self::$imageGenerationMode = self::$ImagickExists
                ? self::USE_IMAGICK
                : (self::$GdExists ? self::USE_GD : false);
        }

        if (self::$imageGenerationMode === false) {
            throw new UnsupportedAdapter(
                'Extension gd or imagick has not been installed on the system.'
            );
        }
    }

    /**
     * @param string $file
     *
     * @return ImageAdapterInterface
     */
    public function createFromFile(string $file) : ImageAdapterInterface
    {
        if (!is_file($file)) {
            throw new ImageFileNotFoundException($file);
        }
        return self::$imageGenerationMode === self::USE_IMAGICK
            ? new Imagick($file)
            : new Gd($file);
    }

    public function createFromStream(StreamInterface $stream): ImageAdapterInterface
    {
        return self::$imageGenerationMode === self::USE_IMAGICK
            ? new Imagick($stream)
            : new Gd($stream);
    }
}
