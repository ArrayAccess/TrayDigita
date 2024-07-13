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
    /**
     * @var int use the gd extension
     */
    public const USE_GD = 1;

    /**
     * @var int use the imagick extension
     */
    public const USE_IMAGICK = 2;

    /**
     * @var int|false|null USE_GD|USE_IMAGICK
     */
    private static int|null|false $imageGenerationMode = null;

    /**
     * @var bool $GdExists gd extension exists
     */
    private static bool $GdExists = false;

    /**
     * @var bool $ImagickExists imagick extension exists
     */
    private static bool $ImagickExists = false;

    /**
     * ImageResizerFactory constructor.
     * @throws UnsupportedAdapter
     */
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
     * @inheritdoc
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

    /**
     * @inheritdoc
     */
    public function createFromStream(StreamInterface $stream): ImageAdapterInterface
    {
        return self::$imageGenerationMode === self::USE_IMAGICK
            ? new Imagick($stream)
            : new Gd($stream);
    }
}
