<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\HtmlResponderInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Http\ResponseFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Http\StreamFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Stringable;
use Throwable;
use function is_scalar;
use function is_string;
use function print_r;
use function sprintf;
use function trim;

class HtmlResponder implements HtmlResponderInterface
{
    use StreamFactoryTrait,
        ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        ResponseFactoryTrait;

    private string $contentType = 'text/html';

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
        return $this->contentType;
    }

    /**
     * @param ?string $charset
     */
    public function setCharset(?string $charset): void
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

    public function format(int $code, $data): string
    {
        $originalData = $data;
        if (is_scalar($data) || $data instanceof Stringable) {
            $data = (string) $data;
        } else {
            $data = print_r($data, true);
        }
        // @dispatch(htmlResponder.format)
        $newData = $this->getManager()
            ?->dispatch(
                'htmlResponder.format',
                $data,
                $originalData,
                $code
            );
        if (is_string($newData)
            || is_scalar($newData)
            || $newData instanceof Stringable
        ) {
            return (string) $newData;
        }
        return $data;
    }

    public function serve(int $code, mixed $data = null, ?ResponseInterface $response = null): ResponseInterface
    {
        $eventsManager = $this->getManager();
        $response ??= $this->getResponseFactory()?->createResponse($code);
        // @dispatch(htmlResponder.response)
        $newResponse = $eventsManager?->dispatch(
            'htmlResponder.response',
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

        $body->write($this->format($code, $data));
        return $this->appendContentType(
            $response->withStatus($code)->withBody($body)
        );
    }

    public function appendContentType(ResponseInterface $response) : ResponseInterface
    {
        $contentType = $this->getContentType();
        $charset = $this->getCharset();
        if ($charset) {
            $contentType .= sprintf('; charset=%s', $charset);
        }
        return $response->withHeader('Content-Type', $contentType);
    }
}
