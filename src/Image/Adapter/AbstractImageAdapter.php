<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Adapter;

use ArrayAccess\TrayDigita\Http\Stream;
use ArrayAccess\TrayDigita\Image\Exceptions\ImageFileNotFoundException;
use ArrayAccess\TrayDigita\Image\Exceptions\ImageIsNotSupported;
use ArrayAccess\TrayDigita\Image\Factory\ImageResizerFactory;
use ArrayAccess\TrayDigita\Image\Interfaces\ImageAdapterInterface;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Generator\UUID;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
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
     * @var int $imageType image type
     */
    private int $imageType;

    /**
     * @var int $originalHeight original height
     */
    private int $originalHeight;

    /**
     * @var int $originalWidth original width
     */
    private int $originalWidth;

    /**
     * @var string $originalMimeType original mime type
     */
    private string $originalMimeType;

    /**
     * @var string $originalStandardExtension original standard extension
     */
    private string $originalStandardExtension;

    /**
     * @var int $width width
     */
    protected int $width;

    /**
     * @var int $height height
     */
    protected int $height;

    /**
     * The Resource
     *
     * @var resource|\GdImage|\Imagick|null
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    protected mixed $resource = null;

    /**
     * The source
     *
     * @var StreamInterface|string
     */
    private StreamInterface|string $source;

    /**
     * Image adapter constructor.
     *
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
            if (file_exists($streamOrFile) && is_file($streamOrFile)) {
                $size = filesize($streamOrFile);
                $file = $streamOrFile;
            } else {
                if (Consolidation::isBinary($streamOrFile)) {
                    $stream = new Stream(fopen('php://temp', 'r+'));
                    while (strlen($streamOrFile) > 0) {
                        $stream->write(substr($streamOrFile, 0, 8192));
                        $streamOrFile = substr($streamOrFile, 8192);
                    }
                    $file = null;
                    $streamOrFile = $stream;
                    $size = $streamOrFile->getSize();
                    $streamOrFile->rewind();
                } else {
                    throw new ImageIsNotSupported(
                        'Could not determine source size or size zero value.'
                    );
                }
            }
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function getOriginalStandardExtension(): string
    {
        return $this->originalStandardExtension;
    }

    /**
     * @inheritdoc
     */
    public function getImageType(): ?int
    {
        return $this->imageType;
    }

    /**
     * @inheritdoc
     */
    public function getOriginalMimeType(): string
    {
        return $this->originalMimeType;
    }

    /**
     * @inheritdoc
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * @inheritdoc
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * @inheritdoc
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @inheritdoc
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @inheritdoc
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
     * @return StreamInterface|string string is filename
     */
    public function getSource(): StreamInterface|string
    {
        return $this->source;
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
     */
    #[ArrayShape(['height' => "int", 'width' => "int"])]
    public function getPortraitDimension(int $height): array
    {
        return [
            'height' => $height,
            'width' => (int) ceil($height * $this->getOriginalRatio()['height'])
        ];
    }

    /**
     * @inheritdoc
     */
    #[ArrayShape(['height' => "int", 'width' => "int"])]
    public function getLandscapeDimension(int $width): array
    {
        return [
            'height' => (int) ceil($width * $this->getOriginalRatio()['width']),
            'width' => $width
        ];
    }

    /**
     * @inheritdoc
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

    /**
     * @inheritdoc
     */
    public function getOriginalOrientation(): int
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

    /**
     * @inheritdoc
     */
    public function isSquare(): bool
    {
        return $this->getOrientation() === self::MODE_ORIENTATION_SQUARE;
    }

    /**
     * @inheritdoc
     */
    public function isLandscape() : bool
    {
        return $this->getOrientation() === self::MODE_ORIENTATION_LANDSCAPE;
    }

    /**
     * @inheritdoc
     */
    public function isPortrait() : bool
    {
        return $this->getOrientation() === self::MODE_ORIENTATION_PORTRAIT;
    }

    /**
     * @inheritdoc
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

    /**
     * @inheritdoc
     */
    public static function fromStream(StreamInterface $stream, ImageResizerFactory $resizer): static
    {
        return new static($stream);
    }

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * Clear the resource
     */
    abstract protected function clearResource();

    /**
     * Magic method __destruct
     */
    public function __destruct()
    {
        $this->clearResource();
    }
}
