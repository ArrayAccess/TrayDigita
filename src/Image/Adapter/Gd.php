<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Image\Adapter;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Image\Exceptions\ImageIsNotSupported;
use ArrayAccess\TrayDigita\Uploader\Exceptions\DirectoryUnWritAbleException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use GdImage;
use function array_keys;
use function array_search;
use function dirname;
use function file_exists;
use function function_exists;
use function imagealphablending;
use function imagebmp;
use function imagecopy;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagegif;
use function imagejpeg;
use function imagepng;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function imagewbmp;
use function imagewebp;
use function imagexbm;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function is_writable;
use function mkdir;
use function pathinfo;
use function realpath;
use function round;
use function sprintf;
use function strtolower;
use function trim;
use const E_USER_WARNING;
use const IMAGETYPE_PNG;
use const PATHINFO_EXTENSION;

class Gd extends AbstractImageAdapter
{
    /**
     * @var ?array
     */
    private static ?array $supportedMimeTypes = null;

    /**
     * @var ?array
     */
    private ?array $last_set_image = null;

    /**
     * @var ?GdImage
     */
    private ?GdImage $image_resized = null;

    /**
     * @inheritdoc
     */
    public function getSupportedMimeTypeExtensions(): array
    {
        return array_keys(self::getSupportedMimeTypeExtensionsFunctions());
    }

    /**
     * Get supported mime types
     *
     * @return array<string, callable>
     */
    private function getSupportedMimeTypeExtensionsFunctions(): array
    {
        if (self::$supportedMimeTypes === null) {
            self::$supportedMimeTypes = [];
            $mime = [
                'gif' => 'gif',
                'jpeg' => [
                    'jpeg',
                    'x-icon',
                    'image/vnd.microsoft.icon',
                ],
                'xbm' => 'xbm',
                'png' => 'png',
                'webp' => 'webp',
                'bmp' => 'x-xbitmap',
                'wbmp' => 'wbmp',
            ];

            foreach ($mime as $fn => $image) {
                if (function_exists("image$fn") && function_exists("imagecreatefrom$fn")) {
                    if (is_array($image)) {
                        foreach ($image as $img) {
                            self::$supportedMimeTypes["image/$img"] = $fn;
                        }
                        continue;
                    }
                }
                self::$supportedMimeTypes["image/$image"] = $fn;
            }
        }

        return self::$supportedMimeTypes;
    }

    /**
     * @return GdImage
     */
    protected function getResource() : GdImage
    {
        if ($this->resource instanceof GdImage) {
            return $this->resource;
        }

        // $extension = $this->getOriginalStandardExtension();
        $mimeType = $this->getOriginalMimeType();
        $source = $this->getSource();
        if (is_string($source)) {
            $fn = $this->getSupportedMimeTypeExtensionsFunctions()[$mimeType] ?? null;
            if (!$fn) {
                throw new ImageIsNotSupported(
                    sprintf('Image mimetype %s is not supported', $mimeType)
                );
            }
            $fn = "imagecreatefrom$fn";
            $this->resource = $fn($this->getSource());
        } else {
            $this->resource = imagecreatefromstring((string) $source);
        }
        /**
         * if image type PNG save Alpha Blending
         */
        if ($this->getImageType() === IMAGETYPE_PNG) {
            imagealphablending($this->resource, true); // setting alpha blending on
            imagesavealpha($this->resource, true); // save alpha blending setting (important)
        }
        $this->width  = imagesx($this->resource)?:$this->width;
        $this->height = imagesy($this->resource)?:$this->height;
        return $this->resource;
    }

    /**
     * @inheritdoc
     */
    public function resize(int $width, int $height, int $mode = self::MODE_AUTO, bool $optimize = false): static
    {
        $this->resource = $this->getResource();
        $srcWidth = imagesx($this->resource);
        $srcHeight = imagesy($this->resource);
        $this->last_set_image = [
            'height' => $width,
            'width' => $height,
            'mode' => $mode
        ];
        $dimensions = $this->getDimensions($width, $height, $mode);
        if ($this->image_resized instanceof GdImage) {
            imagedestroy($this->image_resized);
        }
        $offsetX = 0;
        $offsetY = 0;
        if (($mode & self::MODE_CROP) === self::MODE_CROP
            || (!$this->isSquare()
                && ($mode & self::MODE_ORIENTATION_SQUARE) === self::MODE_ORIENTATION_SQUARE
            )
        ) {
            $width = $dimensions['width'];
            $height = $dimensions['height'];
            [
                $tempWidth,
                $tempHeight,
                $offsetX,
                $offsetY
            ] = $this->calculateOffset(
                $srcWidth,
                $srcHeight,
                $width,
                $height
            );
            $resource = $this->resource;
            /*
             * Resize the image into a temporary GD image
             */
            $this->resource = imagecreatetruecolor($tempWidth, $tempHeight);
            imagecopyresampled(
                $this->resource,
                $resource,
                0,
                0,
                0,
                0,
                $tempWidth,
                $tempHeight,
                $srcWidth,
                $srcHeight
            );
            // freed
            imagedestroy($resource);
            $this->image_resized = imagecreatetruecolor($width, $height);
            imagecopy(
                $this->image_resized,
                $this->resource,
                0,
                0,
                $offsetX,
                $offsetY,
                $width,
                $height,
            );
        } else {
            $this->image_resized = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
            imagecopyresampled(
                $this->image_resized,
                $this->resource,
                0,
                0,
                $offsetX,
                $offsetY,
                $dimensions['width'],
                $dimensions['height'],
                $srcWidth,
                $srcHeight,
            );
        }
        imagedestroy($this->resource);
        $this->width  = imagesx($this->image_resized);
        $this->height = imagesy($this->image_resized);
        $this->resource = null;
        return $this;
    }

    /**
     * Save The image result resized
     * @inheritdoc
     * @param string $target Full path of file name eg [/path/of/dir/image/image.jpg]
     * @param integer $quality image quality [1 - 100]
     * @param bool $overwrite force rewrite existing image if there was path exists
     * @param string|null $forceOverrideExtension force using extensions with certain output
     *
     * @return ?array{"width":int,"height":int,"path":string,"type":string} null if on fail otherwise array
     */
    public function saveTo(
        string $target,
        int $quality = 100,
        bool $overwrite = false,
        ?string $forceOverrideExtension = null
    ): ?array {
        // check if it has on cropProcess
        if (! $this->image_resized instanceof GdImage) {
            if (!($this->last_set_image['width']??null)) {
                $this->image_resized = $this->getResource();
            } else {
                // set from last result
                $this->resize(
                    $this->last_set_image['width'],
                    $this->last_set_image['height'],
                    $this->last_set_image['mode']
                );
            }
        }

        // Get extension
        $extension = pathinfo($target, PATHINFO_EXTENSION)?:'';
        // file exist
        if (file_exists($target)) {
            if (!$overwrite) {
                return null;
            }
            if (!is_writable($target)) {
                $this->clearResource();
                throw new RuntimeException(
                    'File exist! And could not to be replace',
                    E_USER_WARNING
                );
            }
        }

        $functions = $this->getSupportedMimeTypeExtensionsFunctions();
        $fn = null;
        if ($forceOverrideExtension) {
            $forceOverrideExtension = strtolower(trim($forceOverrideExtension));
            $key = array_search($forceOverrideExtension, $functions);
            $fn = $key !== false ? $functions[$key] : null;
        }

        $extensionLower = strtolower($extension);
        $fn = $fn??$functions[$extensionLower]??null;
        // check if image output type allowed
        if (!$fn) {
            $fn = $functions[$this->getOriginalMimeType()]??null;
        }
        if (!$fn) {
            $this->clearResource();
            throw new ImageIsNotSupported(
                sprintf('Image extension %s is not supported', $extension)
            );
        }

        $dir_name = dirname($target);
        if (!is_dir($dir_name)) {
            if (!Consolidation::callbackReduceError(
                fn () =>!mkdir($dir_name, 0755, true)
            )) {
                $dir_name = null;
            }
        }

        if (!$dir_name) {
            $this->clearResource();
            throw new DirectoryUnWritAbleException(
                dirname($target),
                'Directory Target Does not exist. Resource image resize cleared.',
            );
        }
        if (!is_writable($dir_name)) {
            $this->clearResource();
            throw new DirectoryUnWritAbleException(
                $dir_name,
                'Directory Target is not writable. Please check directory permission.'
            );
        }
        // normalize
        $quality = $quality < 10
            ? $quality * 100
            : $quality;
        $ret_val = false;
        switch ($fn) {
            case 'jpeg':
                $ret_val = imagejpeg($this->image_resized, $target, $quality);
                break;
            case 'wbmp':
                $ret_val = imagewbmp($this->image_resized, $target);
                break;
            case 'bmp':
                $ret_val = imagebmp($this->image_resized, $target);
                break;
            case 'gif':
                $ret_val = imagegif($this->image_resized, $target);
                break;
            case 'xbm':
                $ret_val = imagexbm($this->image_resized, $target);
                break;
            case 'webp':
                $ret_val = imagewebp($this->image_resized, $target, $quality);
                break;
            case 'png':
                $scaleQuality = $quality > 9
                    ? round(($quality / 100) * 9)
                    : $quality;
                $invertScaleQuality = 9 - $scaleQuality;
                $ret_val = imagepng(
                    $this->image_resized,
                    $target,
                    (int) $invertScaleQuality
                );
                break;
        }

        $width  = imagesx($this->image_resized);
        $height = imagesy($this->image_resized);
        $path   = is_file($target) ? realpath($target) : $target;

        // destroy resource to make memory free
        imagedestroy($this->image_resized);
        $this->image_resized = null;

        return ! $ret_val ? null : [
            'width' => $width,
            'height' => $height,
            'path' => $path,
            'type' => $fn,
        ];
    }

    /**
     * @inheritdoc
     */
    protected function clearResource(): void
    {
        if ($this->resource instanceof GdImage) {
            imagedestroy($this->resource);
        }
        if ($this->image_resized instanceof GdImage) {
            imagedestroy($this->image_resized);
        }

        $this->resource = null;
        $this->image_resized = null;
    }
}
