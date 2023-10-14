<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Interfaces;

use ArrayAccess\TrayDigita\Image\Factory\ImageResizerFactory;
use Psr\Http\Message\StreamInterface;

interface ImageAdapterInterface
{
    const MODE_AUTO = 1;
    const MODE_CROP = 2;
    const MODE_ORIENTATION_LANDSCAPE = 3;
    const MODE_ORIENTATION_PORTRAIT = 4;
    const MODE_ORIENTATION_SQUARE = 5;

    const IMAGE_TYPE_LIST = [
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_JPEG2000 => 'jpe',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_SWF => 'swf',
        IMAGETYPE_PSD => 'psd',
        IMAGETYPE_BMP => 'bmp',
        IMAGETYPE_TIFF_II => 'tiff', # intel byte order
        IMAGETYPE_TIFF_MM => 'tiff', # motorola byte order
        IMAGETYPE_JP2 => 'jp2',
        IMAGETYPE_JPX => 'jpx',
        IMAGETYPE_JB2 => 'jb2',
        IMAGETYPE_SWC => 'swc',
        IMAGETYPE_IFF => 'iff',
        IMAGETYPE_WBMP => 'wbmp',
        IMAGETYPE_XBM => 'xbm',
        IMAGETYPE_ICO => 'ico',
        IMAGETYPE_WEBP => 'webp'
    ];

    const MIME_TYPES = [
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
     * @return int new width
     */
    public function getWidth() : int;

    /**
     * @return int new height
     */
    public function getHeight() : int;

    /**
     * @return array<string>
     */
    public function getSupportedMimeTypeExtensions() : array;

    /**
     * @param string $mimeType
     *
     * @return bool
     */
    public function isMimeTypeSupported(string $mimeType) : bool;

    /**
     * @return int returning null if file / resource invalid
     */
    public function getOriginalWidth() : int;

    /**
     * @return int returning null if file / resource invalid
     */
    public function getOriginalHeight() : int;

    /**
     * @return array{"width":float,"height":float}
     */
    public function getRatio() : array;

    /**
     * @return array
     */
    public function getOriginalRatio() : array;

    /**
     * @return int MODE_ORIENTATION_LANDSCAPE|MODE_ORIENTATION_PORTRAIT|MODE_ORIENTATION_SQUARE
     */
    public function getOrientation() : int;

    /**
     * @return int MODE_ORIENTATION_LANDSCAPE|MODE_ORIENTATION_PORTRAIT|MODE_ORIENTATION_SQUARE
     */
    public function getOriginalOrientation() : int;

    /**
     * @param int $width
     * @param int $height
     * @param int $mode
     *
     * @return array{"width":int,"height":int}
     */
    public function getDimensions(int $width, int $height, int $mode = self::MODE_AUTO) : array;

    /**
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    public function getPortraitDimension(int $height) : array;

    /**
     * @param int $width
     *
     * @return array{"width":int,"height":int}
     */
    public function getLandscapeDimension(int $width) : array;

    /**
     * @param int $width
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    public function getAutoDimension(int $width, int $height) : array;

    /**
     * Resize image
     *
     * @param int $width
     * @param int $height
     * @param int $mode
     * @param bool $optimize
     * @return static
     */
    public function resize(int $width, int $height, int $mode = self::MODE_AUTO, bool $optimize = false) : static;

    /**
     * @param string $target
     * @param int $quality
     * @param false $overwrite
     * @param ?string $forceOverrideExtension
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
     * @param StreamInterface $stream
     * @param ImageResizerFactory $resizer
     *
     * @return static
     */
    public static function fromStream(StreamInterface $stream, ImageResizerFactory $resizer) : static;

    /**
     * @param string $imageFile
     * @param ImageResizerFactory $resizer
     *
     * @return static
     */
    public static function fromFile(string $imageFile, ImageResizerFactory $resizer) : static;
}
