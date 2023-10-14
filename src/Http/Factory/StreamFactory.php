<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Factory;

use ArrayAccess\TrayDigita\Http\Stream;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class StreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        // $file = Manager::dispatch('streamFactory.createStream', 'php://temp');
        $stream = $this->createStreamFromFile('php://temp', 'r+');
        $content !== '' && $stream->write($content);
        return $stream;
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return Stream::fromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }
}
