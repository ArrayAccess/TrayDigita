<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Adapter;

use ArrayAccess\TrayDigita\Http\Stream;
use ArrayAccess\TrayDigita\Image\Exceptions\ImageFileNotFoundException;
use ArrayAccess\TrayDigita\Image\Exceptions\ImageIsNotSupported;
use ArrayAccess\TrayDigita\Image\Factory\ImageResizerFactory;
use ArrayAccess\TrayDigita\Image\Interfaces\ImageAdapterInterface;
use ArrayAccess\TrayDigita\Util\Generator\UUID;
use GdImage;
use Imagick as ImagickAlias;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\StreamInterface;
use function file_exists;
use function filesize;
use function fopen;
use function getimagesize;
use function getimagesizefromstring;
use function is_array;
use function is_file;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;

abstract class AbstractImageAdapter implements ImageAdapterInterface
{
    /**
     * @var int 10MB Default
     */
    protected int $maximumSourceSize = 10485760;

    /**
     * @var ?int
     */
    private ?int $imageType;

    /**
     * @var int
     */
    private int $originalHeight;

    /**
     * @var int
     */
    private int $originalWidth;

    /**
     * @var string
     */
    private string $originalMimeType;

    /**
     * @var string
     */
    private string $originalStandardExtension;

    /**
     * @var int
     */
    protected int $width;

    /**
     * @var int
     */
    protected int $height;

    /**
     * @var resource|GdImage|ImagickAlias
     */
    protected mixed $resource = null;

    /**
     * @var StreamInterface|string
     */
    private StreamInterface|string $source;

    /**
     * @param StreamInterface|string $streamOrFile
     */
    final public function __construct(
        StreamInterface|string $streamOrFile
    ) {
        if ($streamOrFile instanceof StreamInterface) {
            if (!$streamOrFile->isReadable()) {
                throw new ImageIsNotSupported(
                    'Source stream is not readable.'
                );
            }

            $offset = $streamOrFile->tell();
            if ($offset > 0) {
                if (!$streamOrFile->isSeekable()) {
                    throw new ImageIsNotSupported(
                        'Source stream source is not seekable but going to offset : ' . $offset
                    );
                }
                $streamOrFile->rewind();
            }
            // use file if possible
            $file = $streamOrFile->getMetadata('uri');
            $file = $file && file_exists($file) ? $file : null;
            $size = $file ? filesize($file) : $streamOrFile->getSize();
        } else {
            if (!file_exists($streamOrFile) || !is_file($streamOrFile)) {
                throw new ImageFileNotFoundException(
                    $streamOrFile
                );
            }
            $size = filesize($streamOrFile);
            $file = $streamOrFile;
        }

        if ($size <= 0) {
            throw new ImageIsNotSupported(
                'Could not determine source size or size zero value.'
            );
        }
        if ($size > $this->maximumSourceSize) {
            throw new ImageIsNotSupported(
                sprintf(
                    'Image size too large than allowed is: %d bytes',
                    $this->maximumSourceSize
                )
            );
        }

        $this->source = $file??$streamOrFile;
        $imageSize = $file
            ? getimagesize($file)
            : getimagesizefromstring((string) $streamOrFile);
        if (!is_array($imageSize)) {
            throw new ImageIsNotSupported(
                'Could not determine image type & size.'
            );
        }

        $this->originalWidth = $imageSize[0];
        $this->originalHeight = $imageSize[1];
        $mimeType = $imageSize['mime'];
        $imageType = ($imageSize[2]??null)?:null;
        if ($imageType === null || $imageType === IMAGETYPE_UNKNOWN) {
            throw new ImageIsNotSupported(
                'Could not determine image type.'
            );
        }

        if (!isset(self::MIME_TYPES[$mimeType])
            || !$this->isMimeTypeSupported($mimeType)
        ) {
            throw new InvalidArgumentException(
                sprintf('%s is not supported', $mimeType)
            );
        }

        $this->originalWidth = $imageSize[0];
        $this->originalHeight = $imageSize[1];
        $this->originalMimeType = $mimeType;
        $this->imageType = $imageType;
        $this->width = $this->originalWidth;
        $this->height = $this->originalHeight;
        if (isset(self::IMAGE_TYPE_LIST[$this->imageType])) {
            $this->originalStandardExtension = self::IMAGE_TYPE_LIST[$this->imageType];
        } else {
            $ext = self::MIME_TYPES[$mimeType]??[];
            $this->originalStandardExtension = reset($ext)?:null;
        }
    }

    /**
     * @param int $sourceWidth
     * @param int $sourceHeight
     * @param int $desiredWidth
     * @param int $desiredHeight
     *
     * @return array{"0":int,"1":int,"2":int,"3":int,"4":float,"5":array<array>}
     */
    public function calculateOffset(
        int $sourceWidth,
        int $sourceHeight,
        int $desiredWidth,
        int $desiredHeight
    ): array {
        $source_aspect_ratio = $sourceWidth / $sourceHeight;
        $desired_aspect_ratio = $desiredWidth / $desiredHeight;
        if ($source_aspect_ratio > $desired_aspect_ratio) {
            /*
             * Triggered when source image is wider
             */
            $scaledHeight = $desiredHeight;
            $scaledWidth = ($desiredHeight * $source_aspect_ratio);
        } else {
            /*
             * Triggered otherwise (i.e. source image is similar or taller)
             */
            $scaledWidth = $desiredWidth;
            $scaledHeight = ($desiredWidth / $source_aspect_ratio);
        }
        $offsetX = (int) (($scaledWidth - $desiredWidth) / 2);
        $offsetY = ( int ) (($scaledHeight - $desiredHeight) / 2);
        return [
            (int) round($scaledWidth),
            (int) round($scaledHeight),
            $offsetX,
            $offsetY,
            (float) ($scaledWidth / $scaledHeight),
            [
                'source' => [
                    'aspect_ratio' => $source_aspect_ratio,
                    'width' => $sourceWidth,
                    'height' => $sourceHeight,
                ],
                'desired' => [
                    'aspect_ratio' => $desired_aspect_ratio,
                    'width' => $desiredWidth,
                    'height' => $desiredHeight,
                ]
            ],
        ];
    }

    /**
     * @return string
     */
    public function getOriginalStandardExtension(): string
    {
        return $this->originalStandardExtension;
    }

    /**
     * @return int
     */
    public function getImageType(): mixed
    {
        return $this->imageType;
    }

    /**
     * @return string
     */
    public function getOriginalMimeType(): mixed
    {
        return $this->originalMimeType;
    }

    /**
     * @return int
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * @return int
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @param string $mimeType image/png, image/jpeg ... etc or jpg|png|jpeg
     *
     * @return bool
     */
    public function isMimeTypeSupported(string $mimeType): bool
    {
        $mimeType = strtolower(trim($mimeType));
        if (strpos($mimeType, '/')) {
            preg_match('~^image/([^;\s]+)(;|$)~i', $mimeType, $match);
            if (!$match) {
                return false;
            }
            $mimeType = $match[1];
            if (!$mimeType) {
                return false;
            }
        }
        $mimeType = "image/$mimeType";
        if (!isset(self::MIME_TYPES["image/$mimeType"])) {
            foreach (self::MIME_TYPES as $mime => $item) {
                if (in_array($mimeType, $item)) {
                    $mimeType = $mime;
                    break;
                }
            }
        }
        return in_array(
            $mimeType,
            $this->getSupportedMimeTypeExtensions(),
            true
        );
    }

    /**
     * @return StreamInterface|string
     */
    public function getSource(): StreamInterface|string
    {
        return $this->source;
    }

    /**
     * @return array{"width":float,"height":float}
     */
    public function getRatio(): array
    {
        $width = $this->getWidth();
        $height = $this->getHeight();
        return [
            'width' => (float) ($height / $width),
            'height' => (float) ($width / $height),
        ];
    }

    /**
     * @return array{"width":float,"height":float}
     */
    public function getOriginalRatio(): array
    {
        $width = $this->getOriginalWidth();
        $height = $this->getOriginalHeight();
        return [
            'width' => (float) ($height / $width),
            'height' => (float) ($width / $height),
        ];
    }

    /**
     * @param int $width
     * @param int $height
     * @param int $mode
     *
     * @return array{"width":int,"height":int}
     */
    public function getDimensions(int $width, int $height, int $mode = self::MODE_AUTO): array
    {
        return match ($mode) {
            self::MODE_AUTO => $this->getAutoDimension($width, $height),
            self::MODE_ORIENTATION_LANDSCAPE => $this->getLandscapeDimension($width),
            self::MODE_ORIENTATION_PORTRAIT => $this->getPortraitDimension($height),
            self::MODE_ORIENTATION_SQUARE => [
                'width'  => $width,
                'height' => $width
            ],
            default => [
                'width' => $width,
                'height' => $height
            ]
        };
    }

    /**
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    #[ArrayShape(['height' => "int", 'width' => "int"])]
    public function getPortraitDimension(int $height): array
    {
        return [
            'height' => $height,
            'width' => (int) ceil($height * $this->getOriginalRatio()['height'])
        ];
    }

    #[ArrayShape(['height' => "int", 'width' => "int"])]
    public function getLandscapeDimension(int $width): array
    {
        return [
            'height' => (int) ceil($width * $this->getOriginalRatio()['width']),
            'width' => $width
        ];
    }

    /**
     * @return int
     */
    public function getOrientation(): int
    {
        $width = $this->getHeight();
        $height = $this->getWidth();
        if ($width === $height) {
            return self::MODE_ORIENTATION_SQUARE;
        }

        return $width < $height
            ? self::MODE_ORIENTATION_LANDSCAPE
            : self::MODE_ORIENTATION_PORTRAIT;
    }

    #[Pure] public function getOriginalOrientation(): int
    {
        $originalHeight = $this->getOriginalHeight();
        $originalWidth = $this->getOriginalWidth();
        if ($originalHeight === $originalWidth) {
            return self::MODE_ORIENTATION_SQUARE;
        }

        return $originalHeight < $originalWidth
            ? self::MODE_ORIENTATION_LANDSCAPE
            : self::MODE_ORIENTATION_PORTRAIT;
    }

    public function isSquare(): bool
    {
        return $this->getOrientation() === self::MODE_ORIENTATION_SQUARE;
    }

    public function isLandscape() : bool
    {
        return $this->getOrientation() === self::MODE_ORIENTATION_LANDSCAPE;
    }

    public function isPortrait() : bool
    {
        return $this->getOrientation() === self::MODE_ORIENTATION_PORTRAIT;
    }

    /**
     * @param int $width
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    public function getAutoDimension(int $width, int $height): array
    {
        $originalOrientations = $this->getOriginalOrientation();
        if ($originalOrientations === self::MODE_ORIENTATION_LANDSCAPE) {
            return $this->getLandscapeDimension($width);
        }
        if ($originalOrientations === self::MODE_ORIENTATION_PORTRAIT) {
            return $this->getPortraitDimension($width);
        }

        $orientation = $this->getOrientation();
        if ($orientation === self::MODE_ORIENTATION_LANDSCAPE) {
            return $this->getLandscapeDimension($width);
        }
        if ($orientation === self::MODE_ORIENTATION_PORTRAIT) {
            return $this->getPortraitDimension($width);
        }
        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    public static function fromStream(StreamInterface $stream, ImageResizerFactory $resizer): static
    {
        return new static($stream);
    }

    public static function fromFile(string $imageFile, ImageResizerFactory $resizer): static
    {
        if (!is_file($imageFile)) {
            throw new ImageFileNotFoundException(
                sprintf('%s has not found', $imageFile)
            );
        }
        $stream = new Stream(fopen($imageFile, 'rb'));
        return new static($stream);
    }

    public function saveToStream(
        string $extension,
        int $quality = 100
    ): ?array {
        $file = tempnam(sys_get_temp_dir(), UUID::v4());
        $saved = $this->saveTo($file, $quality, true, $extension);
        unset($saved['path']);
        $saved['stream'] = new Stream(fopen($file, 'rb'));
        return $saved;
    }

    abstract protected function clearResource();

    public function __destruct()
    {
        $this->clearResource();
    }
}
