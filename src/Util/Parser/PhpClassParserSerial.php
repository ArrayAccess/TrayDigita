<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Parser;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Logical\OutOfRangeException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\UnsupportedRuntimeException;
use ArrayAccess\TrayDigita\Http\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Serializable;
use SplFileInfo;
use function file_exists;
use function is_string;
use function preg_match;
use function serialize;
use function sprintf;
use function trim;
use function unserialize;

final class PhpClassParserSerial implements Serializable
{
    // 500KB
    public const MAX_FILE_SIZE = 512000;

    private static ?Parser $parser = null;

    /**
     * @var string|array<\PhpParser\Node\Stmt|\PhpParser\NodeAbstract>|null
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    private string|array|null $source;

    private function __construct(string $source)
    {
        $this->source = $source;
    }

    /** @noinspection PhpUnused */
    public static function fromSource(string $source): PhpClassParserSerial
    {
        $source = trim($source);
        if (!$source || !preg_match('~^<\?php~i', $source)) {
            unset($source);
            throw new UnsupportedArgumentException(
                'The source maybe is not php class file',
            );
        }

        return new self($source);
    }

    public static function fromFile(string $file): PhpClassParserSerial
    {
        if (!file_exists($file)) {
            throw new FileNotFoundException(
                $file
            );
        }
        return self::fromFileInfo(new SplFileInfo($file));
    }

    public static function fromFileInfo(SplFileInfo $spl): PhpClassParserSerial
    {
        $realpath = $spl->getRealPath();
        if (!$spl->isReadable()) {
            throw new UnsupportedRuntimeException(
                sprintf(
                    '%s is not readable',
                    $realpath
                )
            );
        }
        if (!$spl->isFile()) {
            throw new UnsupportedArgumentException(
                sprintf(
                    '%s is not a file',
                    $realpath
                )
            );
        }

        if ($spl->getSize() > self::MAX_FILE_SIZE) {
            throw new OutOfRangeException(
                sprintf(
                    '%s is too big! Maximum file is %s',
                    $realpath,
                    Consolidation::sizeFormat(self::MAX_FILE_SIZE)
                )
            );
        }

        $object  = $spl->openFile();
        $string = $object->fread(4096);
        if (!$string || !preg_match('~^\s*<\?php~i', $string)) {
            unset($object, $string);
            throw new UnsupportedRuntimeException(
                sprintf(
                    '%s maybe is not php file',
                    $realpath
                )
            );
        }
        while (!$object->eof()) {
            $string .= $object->fread(4096);
        }
        unset($object);
        return new self($string);
    }

    public static function getParser(): ?Parser
    {
        return self::$parser ??= (new ParserFactory())->create(
            ParserFactory::PREFER_PHP7
        );
    }

    public function getSource(): array
    {
        if (is_string($this->source)) {
            $this->source = self::getParser()->parse($this->source);
        }

        return $this->source;
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __serialize(): array
    {
        return [
            'source' => $this->source
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->source = $data['source'];
    }
}
