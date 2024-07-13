<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Interfaces;

use ArrayAccess\TrayDigita\Image\Factory\ImageResizerFactory;
use Psr\Http\Message\StreamInterface;

/**
 * Image adapter interface
 */
interface ImageAdapterInterface
{
    /**
     * @var int MODE_AUTO auto mode
     */
    public const MODE_AUTO = 1;

    /**
     * @var int MODE_CROP crop mode
     */
    public const MODE_CROP = 2;

    /**
     * @var int MODE_ORIENTATION_LANDSCAPE landscape mode
     */
    public const MODE_ORIENTATION_LANDSCAPE = 3;

    /**
     * @var int MODE_ORIENTATION_PORTRAIT portrait mode
     */
    public const MODE_ORIENTATION_PORTRAIT = 4;

    /**
     * @var int MODE_ORIENTATION_SQUARE square mode
     */
    public const MODE_ORIENTATION_SQUARE = 5;

    /**
     * @var array<int, string> list of image type
     */
    public const IMAGE_TYPE_LIST = [
        IMAGETYPE_GIF => 'gif', # gif
        IMAGETYPE_JPEG => 'jpg', # jpg
        IMAGETYPE_JPEG2000 => 'jpe', # jp2
        IMAGETYPE_PNG => 'png', # png
        IMAGETYPE_SWF => 'swf', # swf
        IMAGETYPE_PSD => 'psd', # psd
        IMAGETYPE_BMP => 'bmp', # bmp
        IMAGETYPE_TIFF_II => 'tiff', # intel byte order
        IMAGETYPE_TIFF_MM => 'tiff', # motorola byte order
        IMAGETYPE_JP2 => 'jp2', # jp2
        IMAGETYPE_JPX => 'jpx', # jpx
        IMAGETYPE_JB2 => 'jb2', # jb2
        IMAGETYPE_SWC => 'swc', # swc
        IMAGETYPE_IFF => 'iff', # iff
        IMAGETYPE_WBMP => 'wbmp', # wbmp
        IMAGETYPE_XBM => 'xbm', # xbm
        IMAGETYPE_ICO => 'ico', # ico
        IMAGETYPE_WEBP => 'webp' # webp
    ];

    /**
     * @var array<string, array<string>> list of mime types
     */
    public const MIME_TYPES = [
        "image/bmp" => [
            "bmp"
        ],
        "image/cgm" => [
            "cgm"
        ],
        "image/g3fax" => [
            "g3"
        ],
        "image/gif" => [
            "gif"
        ],
        "image/ief" => [
            "ief"
        ],
        "image/jpeg" => [
            "jpg",
            "jpeg",
            "jpe"
        ],
        "image/ktx" => [
            "ktx"
        ],
        "image/png" => [
            "png"
        ],
        "image/prs.btif" => [
            "btif"
        ],
        "image/sgi" => [
            "sgi"
        ],
        "image/svg+xml" => [
            "svg",
            "svgz"
        ],
        "image/tiff" => [
            "tiff",
            "tif"
        ],
        "image/vnd.adobe.photoshop" => [
            "psd"
        ],
        "image/vnd.dece.graphic" => [
            "uvi",
            "uvvi",
            "uvg",
            "uvvg"
        ],
        "image/vnd.djvu" => [
            "djvu",
            "djv"
        ],
        "image/vnd.dvb.subtitle" => [
            "sub"
        ],
        "image/vnd.dwg" => [
            "dwg"
        ],
        "image/vnd.dxf" => [
            "dxf"
        ],
        "image/vnd.fastbidsheet" => [
            "fbs"
        ],
        "image/vnd.fpx" => [
            "fpx"
        ],
        "image/vnd.fst" => [
            "fst"
        ],
        "image/vnd.fujixerox.edmics-mmr" => [
            "mmr"
        ],
        "image/vnd.fujixerox.edmics-rlc" => [
            "rlc"
        ],
        "image/vnd.ms-modi" => [
            "mdi"
        ],
        "image/vnd.ms-photo" => [
            "wdp"
        ],
        "image/vnd.net-fpx" => [
            "npx"
        ],
        "image/vnd.wap.wbmp" => [
            "wbmp"
        ],
        "image/vnd.xiff" => [
            "xif"
        ],
        "image/webp" => [
            "webp"
        ],
        "image/x-3ds" => [
            "3ds"
        ],
        "image/x-cmu-raster" => [
            "ras"
        ],
        "image/x-cmx" => [
            "cmx"
        ],
        "image/x-freehand" => [
            "fh",
            "fhc",
            "fh4",
            "fh5",
            "fh7"
        ],
        "image/x-icon" => [
            "ico"
        ],
        "image/vnd.microsoft.icon" => [
            "ico"
        ],
        "image/x-mrsid-image" => [
            "sid"
        ],
        "image/x-pcx" => [
            "pcx"
        ],
        "image/x-pict" => [
            "pic",
            "pct"
        ],
        "image/x-portable-anymap" => [
            "pnm"
        ],
        "image/x-portable-bitmap" => [
            "pbm"
        ],
        "image/x-portable-graymap" => [
            "pgm"
        ],
        "image/x-portable-pixmap" => [
            "ppm"
        ],
        "image/x-rgb" => [
            "rgb"
        ],
        "image/x-tga" => [
            "tga"
        ],
        "image/x-xbitmap" => [
            "xbm"
        ],
        "image/x-xpixmap" => [
            "xpm"
        ],
        "image/x-xwindowdump" => [
            "xwd"
        ]
    ];

    /**
     * Calculate the offset
     *
     * @param int $sourceWidth source width
     * @param int $sourceHeight source height
     * @param int $desiredWidth desired width
     * @param int $desiredHeight desired height
     *
     * @return array{"0":int,"1":int,"2":int,"3":int,"4":float,"5":array<array>}
     */
    public function calculateOffset(int $sourceWidth, int $sourceHeight, int $desiredWidth, int $desiredHeight) : array;

    /**
     * Get the original standard extension
     *
     * @return string the original file extension
     */
    public function getOriginalStandardExtension(): string;

    /**
     * Get image type
     *
     * @return int|null the image type
     */
    public function getImageType(): ?int;

    /**
     * Get original mime type
     *
     * @return string the original mime type
     */
    public function getOriginalMimeType(): string;

    /**
     * Get current image width
     *
     * @return int new width
     */
    public function getWidth() : int;

    /**
     * Get current image height
     *
     * @return int new height
     */
    public function getHeight() : int;

    /**
     * Get supported mime type extensions
     *
     * @return array<string>
     */
    public function getSupportedMimeTypeExtensions() : array;

    /**
     * Check if mime type supported, eg: jpeg, png, gif etc.
     *
     * @param string $mimeType the mime type
     * @return bool
     */
    public function isMimeTypeSupported(string $mimeType) : bool;

    /**
     * Get original width
     *
     * @return int returning null if file / resource invalid
     */
    public function getOriginalWidth() : int;

    /**
     * Get original height
     *
     * @return int returning null if file / resource invalid
     */
    public function getOriginalHeight() : int;

    /**
     * Get the image ratio
     *
     * @return array{"width":float,"height":float}
     */
    public function getRatio() : array;

    /**
     * Get original ratio
     *
     * @return array the original ratio
     */
    public function getOriginalRatio() : array;

    /**
     * Get orientation
     *
     * @return int MODE_ORIENTATION_LANDSCAPE|MODE_ORIENTATION_PORTRAIT|MODE_ORIENTATION_SQUARE
     */
    public function getOrientation() : int;

    /**
     * Get original orientation
     *
     * @return int MODE_ORIENTATION_LANDSCAPE|MODE_ORIENTATION_PORTRAIT|MODE_ORIENTATION_SQUARE
     */
    public function getOriginalOrientation() : int;

    /**
     * Check if the image is square
     *
     * @return bool
     */
    public function isSquare(): bool;

    /**
     * Check if the image is landscape
     *
     * @return bool
     */
    public function isLandscape() : bool;

    /**
     * Check if the image is portrait
     *
     * @return bool true if portrait
     */
    public function isPortrait() : bool;

    /**
     * Get the dimensions of the image
     *
     * @param int $width the width of the image
     * @param int $height the height of the image
     * @param int $mode the mode of the resize one of:
     *      MODE_AUTO, MODE_CROP, MODE_ORIENTATION_LANDSCAPE, MODE_ORIENTATION_PORTRAIT, MODE_ORIENTATION_SQUARE
     *
     * @return array{"width":int,"height":int}
     */
    public function getDimensions(int $width, int $height, int $mode = self::MODE_AUTO) : array;

    /**
     * Get square dimension
     *
     * @param int $height the height of the image
     *
     * @return array{"width":int,"height":int}
     */
    public function getPortraitDimension(int $height) : array;

    /**
     * Get landscape dimension
     *
     * @param int $width the width of the image
     *
     * @return array{"width":int,"height":int}
     */
    public function getLandscapeDimension(int $width) : array;

    /**
     * Get auto dimension of the image
     *
     * @param int $width the width of the image
     * @param int $height the height of the image
     *
     * @return array{"width":int,"height":int}
     */
    public function getAutoDimension(int $width, int $height) : array;

    /**
     * Resize image
     *
     * @param int $width the width of the image
     * @param int $height the height of the image
     * @param int $mode the mode of the resize one of:
     *      MODE_AUTO, MODE_CROP, MODE_ORIENTATION_LANDSCAPE, MODE_ORIENTATION_PORTRAIT, MODE_ORIENTATION_SQUARE
     * @param bool $optimize optimize the image
     * @return static
     */
    public function resize(
        int $width,
        int $height,
        int $mode = self::MODE_AUTO,
        bool $optimize = false
    ) : ImageAdapterInterface;

    /**
     * Save image to target file
     *
     * @param string $target the target file
     * @param int $quality the quality of the image between 0 and 100
     * @param false $overwrite overwrite the file if exists
     * @param ?string $forceOverrideExtension force override the extension
     *
     * @return ?array{"width":int,"height":int,"path":string,"type":string}
     */
    public function saveTo(
        string $target,
        int $quality = 100,
        bool $overwrite = false,
        ?string $forceOverrideExtension = null
    ) : ?array;

    /**
     * Save image to stream
     *
     * @param string $extension
     * @param int $quality
     *
     * @return ?array{"width":int,"height":int,"stream":StreamInterface,"type":string}
     */
    public function saveToStream(
        string $extension,
        int $quality = 100
    ) : ?array;

    /**
     * Create image from stream
     *
     * @param StreamInterface $stream
     * @param ImageResizerFactory $resizer
     *
     * @return static
     */
    public static function fromStream(StreamInterface $stream, ImageResizerFactory $resizer) : ImageAdapterInterface;

    /**
     * Create image from file
     *
     * @param string $imageFile the image file
     * @param ImageResizerFactory $resizer the image resizer factory
     *
     * @return static
     */
    public static function fromFile(string $imageFile, ImageResizerFactory $resizer) : ImageAdapterInterface;
}
