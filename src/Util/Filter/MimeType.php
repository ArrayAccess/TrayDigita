<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Http\UploadedFile;
use finfo;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use function array_merge;
use function array_search;
use function array_unique;
use function array_values;
use function class_exists;
use function file_exists;
use function function_exists;
use function in_array;
use function is_file;
use function is_string;
use function mime_content_type;
use function pathinfo;
use function realpath;
use function strtolower;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

/**
 * @link https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
 */
final class MimeType
{
    const DEFAULT_MIME_TYPES_FILE =  __DIR__ . '/data/mime.types';
    /**
     * @var ?array
     */
    protected static ?array $extensionMimeTypes = null;

    /**
     * @var string
     */
    protected static string $mimeTypeFile = self::DEFAULT_MIME_TYPES_FILE;

    private const EXTENSION_PREFILL = [
        'application/vnd.etsi.asic-e+zip' => 'asice',
        'application/x-bz2' => 'bz2',
        'text/csv' => 'csv',
        'application/ecmascript' => 'ecma',
        'video/x-flv' => 'flv',
        'image/gif' => 'gif',
        'application/x-gzip' => 'gz',
        'text/html' => 'html',
        'application/x-java-archive' => 'jar',
        'image/jpeg' => 'jpg',
        'text/javascript' => 'js',
        'application/vnd.apple.keynote' => 'keynote',
        'audio/x-mpegurl' => 'm3u',
        'audio/mp4' => 'm4a',
        'text/markdown' => 'md',
        'text/x-markdown' => 'md',
        'application/x-msaccess' => 'mdb',
        'audio/midi' => 'mid',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'application/pdf' => 'pdf',
        'application/x-php' => 'php',
        'text/x-php' => 'php',
        'application/x-sql' => 'sql',
        'application/sql' => 'sql',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/x-rar-compressed' => 'rar',
        'application/stuffit' => 'hqx',
        'application/x-stuffit' => 'sit',
        'image/svg+xml' => 'svg',
        'application/x-tar' => 'tar',
        'image/tiff' => 'tif',
        'application/x-font-truetype' => 'ttf',
        'text/x-vcard' => 'vcf',
        'audio/wav' => 'wav',
        'audio/x-ms-wma' => 'wma',
        'audio/x-ms-wmv' => 'wmv',
        'application/vnd.ms-excel' => 'xls',
        'application/zip' => 'zip',
    ];

    /**
     * Get Mime types List
     *
     * @return array{string, array{main: bool, extensions: array<string>}}
     */
    public static function getExtensionMimeTypes(): array
    {
        if (self::$extensionMimeTypes === null) {
            $mimeFile = self::$mimeTypeFile;
            if (!file_exists($mimeFile)) {
                throw new RuntimeException(
                    'File mime.types is not exists',
                    E_COMPILE_ERROR
                );
            }
            self::$extensionMimeTypes = [];
            $sock = @fopen($mimeFile, 'r');
            $data = '';
            while (!feof($sock)) {
                $data .= fread($sock, 4096);
            }
            fclose($sock);
            $data = trim(preg_replace('~^\s*#([^=]+)[^\n]+\r?\n~', '', $data));
            $data = str_replace(["\r\n", "\n\n"], "\n", $data);
            preg_match_all(
                '~
                    (?:\#\s+)?
                    (?P<mimetype>
                        \S+/(?:
                            \S+[+](?P<alt_ext>[a-z0-9]+)
                            | (?P<alt_ext_2>[a-z]+)-[^\s+]+
                            | (?P<alt_ext_3>[a-z]+)
                            |[^\s+]+
                        )
                    )
                    (?:[ \t]+(?P<ext>[^\n]+))?(?:\n|$)
                ~sx',
                $data,
                $match
            );
            unset($data);
            foreach ($match['mimetype'] as $key => $item) {
                $ext = false;
                if (!empty($match['ext'][$key])) {
                    $current = str_replace(['  ', "\t", "\t\t", " "], '|', $match['ext'][$key]);
                    $ext = ['main' => true, 'extensions' => explode('|', $current)];
                } elseif (!empty($match['alt_ext'][$key])) {
                    $current = str_replace(['  ', "\t", "\t\t", " "], '|', $match['alt_ext'][$key]);
                    $ext = ['main' => false, 'extensions' => explode('|', $current)];
                } elseif (!empty($match['alt_ext_2'][$key])) {
                    $current = str_replace(['  ', "\t", "\t\t", " "], '|', $match['alt_ext_2'][$key]);
                    $ext = ['main' => false, 'extensions' => explode('|', $current)];
                } elseif (!empty($match['alt_ext_3'][$key])) {
                    $current = str_replace(['  ', "\t", "\t\t", " "], '|', $match['alt_ext_3'][$key]);
                    $ext = ['main' => false, 'extensions' => explode('|', $current)];
                }
                if (isset(self::EXTENSION_PREFILL[$item])) {
                    $ext = $ext === false ? [
                        'main' => true,
                        'extensions' => [],
                    ] : $ext;
                    $ext['main'] = true;
                    $ext['extensions'] = [self::EXTENSION_PREFILL[$item]] + $ext['extensions'];
                    $ext['extensions'] = array_values(array_unique($ext['extensions']));
                }
                self::$extensionMimeTypes[$item] = $ext;
            }
        }
        $registered = [];
        foreach (self::EXTENSION_PREFILL as $key => $value) {
            if (!isset(self::$extensionMimeTypes[$key])) {
                self::$extensionMimeTypes[$key] = [
                    'main' => !isset($registered[$value]),
                    'extensions' => [$value]
                ];
                $registered[$value] = true;
            }
        }

        return self::$extensionMimeTypes;
    }

    /**
     * Get Extension List From Mime Type
     *
     * @param string $mime
     *
     * @return array[]|null
     */
    public static function fromMimeType(string $mime): ?array
    {
        $extension = strtolower($mime);
        if (!preg_match('~^[^/]+/[^/]+$~', $mime)) {
            return null;
        }

        $extension = self::getExtensionMimeTypes()[$extension] ?? null;
        return $extension ? $extension['extensions'] : null;
    }

    /**
     * Get List of extension from Mime Type
     *
     * @param string $extension mime type string
     * @return string[]|null
     */
    public static function fromExtension(string $extension) : ?array
    {
        $extension = trim(strtolower($extension));
        if ($extension === '') {
            return null;
        }

        // just make sure for explode
        $extension = explode('.', $extension);
        $extension = end($extension);
        // invalid extension, extensions only valid a-z0-9
        if (preg_match('~[^a-z0-9]~', $extension)) {
            return null;
        }
        $mimes = [];
        $first = [];
        foreach (self::getExtensionMimeTypes() as $key => $value) {
            if (!is_array($value) || empty($value['extensions'])) {
                continue;
            }

            if (in_array($extension, $value['extensions'])) {
                if (reset($value['extensions']) === $extension) {
                    if (!empty($value['main'])) {
                        array_unshift($first, $key);
                    } else {
                        $first[] = $key;
                    }
                }
                $mimes[] = $key;
            }
        }
        if (!empty($first)) {
            $mimes = array_merge($first, $mimes);
            unset($first);
            $mimes = array_values(array_unique($mimes));
        }

        return !empty($mimes) ? $mimes : null;
    }

    /**
     * @param string $extension
     * @return ?string
     */
    public static function mime(string $extension) : ?string
    {
        return self::fromExtension($extension)[0] ?? null;
    }

    /**
     * @param string $mimeType
     * @return ?string
     */
    public static function extension(string $mimeType) : ?string
    {
        return self::EXTENSION_PREFILL[strtolower($mimeType)]
            ??self::fromMimeType($mimeType)[0] ?? null;
    }

    /**
     * Clear Memories
     */
    public static function clear() : void
    {
        self::$extensionMimeTypes = null;
    }

    private static array $uriMimeTypes = [];

    private static null|finfo|false $finfoObjectExists = null;

    /**
     * @return ?finfo
     */
    private static function createFinfo(): ?finfo
    {
        if (self::$finfoObjectExists === null) {
            self::$finfoObjectExists = class_exists('finfo')
                ? new finfo(FILEINFO_MIME_TYPE)
                : false;
        }
        return self::$finfoObjectExists?:null;
    }

    /** @noinspection PhpUnused */
    public static function streamMimeType(StreamInterface $stream) : ?string
    {
        $uri = $stream->getMetadata('uri');
        if ($uri && isset(self::$uriMimeTypes[$uri])) {
            return self::$uriMimeTypes[$uri];
        }

        $info = self::createFinfo();
        $mimeType = $info?->buffer((string) $stream)?:null;
        if ($mimeType && file_exists($uri)) {
            if ($mimeType === 'text/plain' && (
                $ext = pathinfo($uri, PATHINFO_EXTENSION)
                ) && ($key = array_search($ext, self::EXTENSION_PREFILL))
            ) {
                $mimeType = $key;
            }
            self::$uriMimeTypes[$uri] = $mimeType;
            return $mimeType;
        }
        return $mimeType;
    }

    /**
     * @param string $filePath
     * @param ?UploadedFileInterface $uploadedFile
     * @return string|null
     */
    public static function fileMimeType(string $filePath, ?UploadedFileInterface $uploadedFile = null) : ?string
    {
        static $fn_exists = null;

        $filePath = realpath($filePath)?:$filePath;
        if (!is_file($filePath)) {
            return null;
        }

        if (isset(self::$uriMimeTypes[$filePath])) {
            return self::$uriMimeTypes[$filePath]?:null;
        }

        self::$uriMimeTypes[$filePath] = false;
        $finfo = self::createFinfo();
        $mimeType = Consolidation::callbackReduceError(fn() => $finfo?->file($filePath))?:null;
        if (!$mimeType && $fn_exists === null) {
            $fn_exists = function_exists('mime_content_type');
        }
        if ($fn_exists) {
            /**
             * @var ?string $mimeType
             */
            $mimeType = Consolidation::callbackReduceError(fn () => mime_content_type($filePath))?:null;
        }
        if ($mimeType) {
            if ($mimeType === 'text/plain' && (
                    $ext = pathinfo($uploadedFile?->getClientFilename()??$filePath, PATHINFO_EXTENSION)
                ) && ($key = array_search($ext, self::EXTENSION_PREFILL))
            ) {
                $mimeType = $key;
            }
            self::$uriMimeTypes[$filePath] = $mimeType;
            return $mimeType;
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!$extension) {
            return self::$uriMimeTypes[$filePath] = 'application/octet-stream';
        }
        $mimeType = MimeType::mime($extension);
        if ($mimeType) {
            self::$uriMimeTypes[$filePath] = $mimeType;
        }

        return $mimeType;
    }

    public static function mimeTypeUploadedFile(UploadedFileInterface $uploadedFile): ?string
    {
        $uri = $uploadedFile->getStream()->getMetadata('uri');
        if (!is_string($uri) || !is_file($uri)) {
            $fileName = $uploadedFile->getClientFilename();
            if (!$fileName) {
                return null;
            }
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            return is_string($extension)
                ? MimeType::mime($extension)
                : null;
        }
        return self::fileMimeType($uri, $uploadedFile);
    }

    public static function resolveMediaTypeUploadedFiles(
        UploadedFileInterface $uploadedFile
    ): UploadedFile|UploadedFileInterface {
        $mimeType = self::mimeTypeUploadedFile($uploadedFile);
        if (!$mimeType || $uploadedFile->getClientMediaType() === $mimeType) {
            return $uploadedFile;
        }
        return new UploadedFile(
            $uploadedFile->getStream(),
            $uploadedFile->getSize(),
            $uploadedFile->getError(),
            $uploadedFile->getClientFilename(),
            $mimeType
        );
    }

    /**
     * MimeTypes constructor.
     */
    public function __construct()
    {
        self::clear();
    }
}
