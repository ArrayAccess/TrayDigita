<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use function is_array;
use function is_int;

// phpcs:disable PSR1.Files.SideEffects
/**
 * @mixin ChunkHandler
 */
final readonly class StartProgress
{
    public ChunkHandler $handler;

    public function __construct(
        public ChunkProcessor $processor
    ) {
        $this->handler = $processor->getHandler();
        if ($processor->isNewRequestId) {
            $offset = 0;
        } else {
            $ranges = $processor->contentRangeHeader->ranges;
            if (is_array($ranges)) {
                $offset = $ranges[0];
            } else {
                $offset = $this->handler->getSize();
            }
        }
        $this->handler->start($offset);
    }

    public static function create(
        Chunk $chunk,
        UploadedFileInterface $uploadedFile,
        ServerRequestInterface $request
    ) : self {
        return new self(
            $chunk->createProcessor($uploadedFile, $request)
        );
    }

    public function isDone(): bool
    {
        if (!$this->processor->contentRangeHeader->header) {
            return true;
        }
        return $this->handler->getSize() >= $this->processor->contentRangeHeader->size;
    }

    public function getRemainingRequestsCount(): float|int
    {
        if ($this->isDone()) {
            return 0;
        }
        $size = $this->processor->contentRangeHeader->size;
        $ranges = $this->processor->contentRangeHeader->ranges;
        if (!is_int($size) || !is_array($ranges)) {
            return -\INF;
        }
        $calculate = $ranges[1] - $ranges[0];
        $handlerSize = $this->handler->getSize();
        $total = (int) ceil($size / $calculate);
        $totalHandler = (int) ceil($handlerSize / $calculate);
        return $total - $totalHandler;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->handler->$name(...$arguments);
    }

    public function __get(string $name)
    {
        return $this->handler->$name;
    }

    public function __isset(string $name): bool
    {
        return isset($this->handler->$name);
    }
}
