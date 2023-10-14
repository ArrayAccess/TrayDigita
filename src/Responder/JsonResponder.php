<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Http\Code;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonDataResponderInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonResponderInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Http\ResponseFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Http\StreamFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use JsonSerializable;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Stringable;
use Throwable;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function method_exists;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

class JsonResponder implements JsonResponderInterface
{
    use StreamFactoryTrait,
        ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        ResponseFactoryTrait;

    private string $contentType = 'application/json';

    private ?string $charset = null;

    public function __construct(
        ContainerInterface $container = null,
        ManagerInterface $manager = null
    ) {
        if ($container) {
            $this->setContainer($container);
            if (!$manager && $container->has(ManagerInterface::class)) {
                try {
                    $manager = $container->get(ManagerInterface::class);
                } catch (Throwable) {
                }

                if (!$manager instanceof ManagerInterface) {
                    $manager = null;
                }
            }
        }
        if ($manager) {
            $this->setManager($manager);
        }
    }

    public function setContentType(string $contentType): string
    {
        $contentType = trim($contentType);
        preg_match('~^\s*((application/|)\s*)?((?:[^/]+\+)?(json)\s*(;.+))$~i', $contentType, $match);
        if (empty($match)) {
            return $this->contentType;
        }
        $match[1] = strtolower(trim($match[1]));
        if (trim($match[1]) === '') {
            $match[1] = 'application/';
        }
        $this->contentType = "$match[1]/$match[2]";
        return $contentType;
    }

    public function setCharset(?string $charset) : void
    {
        $this->charset = $charset ? (trim($charset)?:null) : null;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @return ?string
     */
    public function getCharset(): ?string
    {
        return $this->charset;
    }

    public function decode(string $data, bool $assoc = true)
    {
        // @dispatch(jsonResponder.decodeDepth)
        $depth = $this->getManager()?->dispatch(
            'jsonResponder.decodeDepth',
            512,
            $data,
            $assoc
        );
        $depth = is_int($depth) ? $depth : 512;
        return json_decode($data, $assoc, $depth);
    }

    public function encode($data): string
    {
        $flags = JSON_UNESCAPED_SLASHES;
        // @dispatch(jsonResponder.encodeFlags)
        $flags = $this->getManager()?->dispatch(
            'jsonResponder.encodeFlags',
            $flags,
            $data
        );
        $flags = is_int($flags) ? $flags : JSON_UNESCAPED_SLASHES;
        // @dispatch(jsonResponder.encodeDepth)
        $depth = $this->getManager()?->dispatch(
            'jsonResponder.encodeDepth',
            512,
            $data,
            $flags
        );
        if (($flags & JSON_THROW_ON_ERROR) !== JSON_THROW_ON_ERROR) {
            $flags |= JSON_THROW_ON_ERROR;
        }

        $depth = is_int($depth) ? $depth : 512;
        return json_encode($data, $flags, $depth);
    }

    public function format(int $code, $data, bool $forceDebug = false): array
    {
        $originalData = $data;
        if ($code < 400) {
            if ($originalData instanceof JsonDataResponderInterface) {
                $data = [
                    'data' => [
                        'message' => $originalData->getMessage()??Code::statusMessage($code),
                        'meta' => $originalData->getMetadata(),
                    ],
                ];
            } else {
                $data = [
                    'data' => $data
                ];
            }
        } else {
            // make sure message is string
            $httpMessage = Code::statusMessage($code);
            if ($data === null) {
                $httpMessage = $httpMessage??sprintf('Error %d', $code);
            }
            if ($data instanceof JsonDataResponderInterface) {
                $message = $data->getErrorMessage();
                $message ??= $httpMessage;
                $data = [
                    'message' => $message,
                    'meta' => $data->getMetadata()
                ];
                if ($message instanceof Throwable) {
                    $data['message'] = $message->getMessage();
                    if (empty($data['meta']['exception'])) {
                        // @dispatch(jsonResponder.debug)
                        if ($forceDebug === true || $this
                                ->getManager()
                                ?->dispatch(
                                    'jsonResponder.debug',
                                    false
                                ) === true
                        ) {
                            $data['meta']['exception'] = [
                                'message' => $originalData->getMessage(),
                                'file' => $originalData->getFile(),
                                'line' => $originalData->getLine(),
                                'code' => $originalData->getCode(),
                                'trace' => $originalData->getTrace(),
                            ];
                        }
                    }
                }
            } else {
                $data = [
                    'message' => $data
                ];
                if ($originalData instanceof Throwable) {
                    $data['message'] = $originalData->getMessage();
                    // @dispatch(jsonResponder.debug)
                    if ($forceDebug === true || $this
                            ->getManager()
                            ?->dispatch(
                                'jsonResponder.debug',
                                false
                            ) === true
                    ) {
                        $data['meta']['exception'] = [
                            'message' => $originalData->getMessage(),
                            'file' => $originalData->getFile(),
                            'line' => $originalData->getLine(),
                            'code' => $originalData->getCode(),
                            'trace' => $originalData->getTrace(),
                        ];
                    }
                } else {
                    if ($originalData instanceof JsonSerializable) {
                        $originalData = $originalData->jsonSerialize();
                    } elseif ($originalData instanceof Stringable
                        || (is_object($originalData)
                            && method_exists($originalData, '__toString')
                        )
                    ) {
                        $data['message'] = (string)$originalData;
                    }
                    if (is_array($originalData)) {
                        if (is_string($originalData['message'] ?? null)) {
                            $message = $originalData['message'];
                            if (count($originalData) === 1) {
                                $data['message'] = $message;
                            } else {
                                $data = [
                                    'message' => $message,
                                    'meta' => $originalData
                                ];
                            }
                        } else {
                            $data = [
                                'message' => $httpMessage,
                                'meta' => $originalData
                            ];
                        }
                    }
                }
            }

            if (!is_string($data['message'])) {
                $oldData = $data;
                $data = [
                    'message' => $httpMessage,
                ];
                if ($oldData['message'] !== null) {
                    $data['meta'] = $oldData['message'];
                }
            }
        }

        // @dispatch(jsonResponder.format)
        $newData = $this->getManager()
            ?->dispatch(
                'jsonResponder.format',
                $data,
                $originalData,
                $code
            );
        return is_array($newData) ? $newData : $data;
    }

    public function serve(
        int $code,
        mixed $data = null,
        ?ResponseInterface $response = null,
        bool $forceDebug = false
    ): ResponseInterface {
        $eventsManager = $this->getManager();
        $response ??= $this->getResponseFactory()?->createResponse($code);
        // @dispatch(jsonResponder.response)
        $newResponse = $eventsManager?->dispatch(
            'jsonResponder.response',
            $code,
            $data
        );
        if ($newResponse instanceof ResponseInterface) {
            $response = $newResponse;
        }

        $body = $response->getBody();
        if (!$body->isWritable() || $body->getSize() > 0) {
            $body = $this->getStreamFactory()->createStream();
        }
        $body->write($this->encode($this->format($code, $data, $forceDebug)));
        $contentType = $this->getContentType();
        $charset = $this->getCharset();
        if ($charset) {
            $contentType .= sprintf('; charset=%s', $charset);
        }
        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', $contentType)
            ->withBody($body);
    }

    public function serveJsonMetadata(
        JsonDataResponderInterface $metadataResponder,
        ?ResponseInterface $response = null,
        bool $forceDebug = false
    ): ResponseInterface {
        return $this->serve(
            $metadataResponder->getStatusCode(),
            $metadataResponder,
            $response,
            $forceDebug
        );
    }
}
