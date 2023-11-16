<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Logical\OutOfRangeException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\ContentRangeIsNotFulFilledException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\InvalidContentRange;
use ArrayAccess\TrayDigita\Uploader\Exceptions\InvalidOffsetPositionException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\InvalidRequestId;
use ArrayAccess\TrayDigita\Uploader\Metadata\ContentRangeHeader;
use ArrayAccess\TrayDigita\Uploader\Metadata\RequestIdHeader;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

final class ChunkProcessor
{
    public const X_REQUEST_ID = 'X-Request-Id';

    public readonly RequestIdHeader $requestIdHeader;

    public readonly bool $isNewRequestId;

    public readonly ContentRangeHeader $contentRangeHeader;

    private ?ChunkHandler $handler = null;

    public function __construct(
        public readonly Chunk $chunk,
        public readonly UploadedFileInterface $uploadedFile,
        ContentRangeHeader|string $contentRangeHeader,
        RequestIdHeader|string|null $requestIdHeader = null
    ) {
        $clientFileName = $this->uploadedFile->getClientFilename();
        if ($clientFileName === null) {
            throw new UnsupportedArgumentException(
                $this->chunk->translateContext(
                    'Uploaded files does not contain file name.',
                    'chunk-uploader'
                )
            );
        }
        if ($requestIdHeader !== null) {
            $this->isNewRequestId = false;
            $this->requestIdHeader = is_string($requestIdHeader)
                ? self::createRequestIdHeader($requestIdHeader)
                : $requestIdHeader;
        } else {
            $this->isNewRequestId = true;
            $this->requestIdHeader = self::createRequestIdHeader(
                RequestIdHeader::createRequestId(
                    $clientFileName
                )
            );
        }
        $this->contentRangeHeader = is_string($contentRangeHeader)
            ? self::createContentRangeHeader($contentRangeHeader)
            : $contentRangeHeader;
    }

    public static function createContentRangeFromRequest(
        ServerRequestInterface $request
    ) : ContentRangeHeader {
        return self::createContentRangeHeader(
            $request->getHeaderLine('Content-Range')
        );
    }

    public static function createRequestIdFromRequest(
        ServerRequestInterface $request
    ) : RequestIdHeader {
        return self::createRequestIdHeader(
            $request->getHeaderLine(self::X_REQUEST_ID)
        );
    }

    public static function createRequestIdHeader($requestIdHeader): RequestIdHeader
    {
        return new RequestIdHeader($requestIdHeader);
    }

    public static function createContentRangeHeader($contentRangeHeader): ContentRangeHeader
    {
        return new ContentRangeHeader($contentRangeHeader);
    }

    /**
     * @param Chunk $chunk
     * @param UploadedFileInterface $uploadedFile
     * @param ServerRequestInterface $request
     * @return self
     */
    public static function createFromRequest(
        Chunk $chunk,
        UploadedFileInterface $uploadedFile,
        ServerRequestInterface $request
    ): self {
        $requestId = null;
        if ($request->hasHeader(self::X_REQUEST_ID)) {
            $requestId = self::createRequestIdFromRequest($request);
        }
        return new self(
            $chunk,
            $uploadedFile,
            self::createContentRangeFromRequest($request),
            $requestId
        );
    }

    /**
     * @return ChunkHandler
     * @throws InvalidRequestId
     * @throws InvalidContentRange
     * @throws ContentRangeIsNotFulFilledException
     * @throws InvalidOffsetPositionException
     * @throws OutOfRangeException
     */
    public function getHandler() : ChunkHandler
    {
        if ($this->handler) {
            return $this->handler;
        }

        $handler = new ChunkHandler($this);
        if ($this->contentRangeHeader->header) {
            if (!$this->contentRangeHeader->valid) {
                throw new InvalidContentRange(
                    sprintf(
                        $this->chunk->translateContext(
                            'Content-Range "%s" is invalid',
                            'chunk-uploader'
                        ),
                        $this->contentRangeHeader->header
                    )
                );
            }

            if (!in_array(
                $this->contentRangeHeader->unit,
                $this->contentRangeHeader->acceptedUnits
            )) {
                throw new InvalidContentRange(
                    sprintf(
                        $this->chunk->translateContext(
                            'Content-Range unit "%s" is invalid',
                            'chunk-uploader'
                        ),
                        $this->contentRangeHeader->unit
                    )
                );
            }

            if (!is_int($this->contentRangeHeader->size)) {
                throw new ContentRangeIsNotFulFilledException(
                    $this->chunk->translateContext(
                        'System does not support unknown size',
                        'chunk-uploader'
                    )
                );
            }
        }

        $size = $this->contentRangeHeader->size;
        $limit = $this->chunk->getLimitMaxFileSize();
        $minimum = $this->chunk->getLimitMinimumFileSize();
        $size = !is_int($size) ? null : $size;
        $ranges = $this->contentRangeHeader->ranges;
        $start  = is_array($ranges) ? $ranges[0] : null;
        $end  = is_array($ranges) ? $ranges[1] : null;
        if ($limit !== null && $limit > 0 && is_int($size) && (
            $size > $limit // limit size total
            || is_int($end) && $end > $limit // limit position
        )) {
            throw new OutOfRangeException(
                sprintf(
                    $this->chunk->translateContext(
                        'Uploaded file size is bigger than allowed size: %s.',
                        'chunk-uploader'
                    ),
                    Consolidation::sizeFormat($limit, 4)
                )
            );
        }

        if ($minimum !== null && is_int($size) && $size > $minimum && (
            $start !== null && $end !== null
            && $minimum > ($end - $start)
            && $size > ($start + $end)
        )) {
            throw new OutOfRangeException(
                sprintf(
                    $this->chunk->translateContext(
                        'Uploaded file size range is less than allowed minimum size: %s.',
                        'chunk-uploader'
                    ),
                    Consolidation::sizeFormat($minimum, 4)
                )
            );
        }

        if ($this->isNewRequestId) {
            if ($start !== null && $start > 0) {
                throw new InvalidOffsetPositionException(
                    $start,
                    $this->contentRangeHeader->size,
                    $this->chunk->translateContext(
                        'Content-Range start bytes must be zero without Request-Id.',
                        'chunk-uploader'
                    )
                );
            }
        } elseif (!$this->requestIdHeader->valid) {
            throw new InvalidRequestId(
                sprintf(
                    $this->chunk->translateContext(
                        'Request id "%s" is not valid',
                        'chunk-uploader'
                    ),
                    $this->requestIdHeader->header
                )
            );
        } elseif ($start !== null && $start <= 0) {
            $handler->deletePartial();
            throw new InvalidOffsetPositionException(
                $start,
                $size,
                $this->chunk->translateContext(
                    'Content-Range start bytes must be greater zero with Request-Id.',
                    'chunk-uploader'
                )
            );
        }

        $stream     = $this->uploadedFile->getStream();
        $streamSize = $stream->getSize();
        $endSize = ($end + 1) - $start;
        if ($endSize < $streamSize) {
            throw new OutOfRangeException(
                $this->chunk->translateContext(
                    'Uploaded file size is bigger than ending size.',
                    'chunk-uploader'
                )
            );
        }

        if ($size !== null && $size < $streamSize) {
            throw new OutOfRangeException(
                $this->chunk->translateContext(
                    'Range size is bigger than file size.',
                    'chunk-uploader'
                )
            );
        }

        return $this->handler = $handler;
    }
}
