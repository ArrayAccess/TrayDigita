<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Generator;

use ArrayAccess\TrayDigita\Auth\Generator\Interfaces\NonceRequestInterface;
use ArrayAccess\TrayDigita\Auth\Generator\Traits\NonceTrait;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use function is_array;

final class RequestNonce implements NonceRequestInterface
{
    use NonceTrait;

    protected array $cachedHash = [];

    protected array $cachedResult = [];

    /**
     * @var ?ServerRequestInterface
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * @inheritdoc
     */
    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * @inheritdoc
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request ??= ServerRequest::fromGlobals(
            ContainerHelper::use(ServerRequestFactoryInterface::class),
            ContainerHelper::use(StreamFactoryInterface::class)
        );
    }

    /**
     * @inheritdoc
     */
    public function createRequestHash(
        string $action,
        ?ServerRequestInterface $request = null
    ): string {
        $ip = $request->getServerParams()['REMOTE_ADDRESS']??'127.0.0.1';
        $userAgent = $request->getHeaderLine('User-Agent');
        return md5($ip.$userAgent.$action);
    }

    /**
     * {@inheritdoc}
     */
    public function generate(
        string $action,
        ?ServerRequestInterface $request = null
    ): string {
        $cacheKey = $this->createRequestHash($action, $request);
        if (isset($this->cachedHash[$cacheKey])) {
            return $this->cachedHash[$cacheKey];
        }
        // cache key as action
        $hash = $this->generateInternalNonce($cacheKey);
        $this->cachedHash[$cacheKey] = $hash;
        $this->cachedResult[$hash][$cacheKey] = self::NONCE_VALID;
        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(
        string $nonce,
        string $action,
        ?ServerRequestInterface $request = null
    ): bool|int {
        $cacheKey = $this->createRequestHash($action, $request);
        /** @noinspection DuplicatedCode */
        $valid = $this->cachedResult[$nonce][$cacheKey]??null;
        if ($valid === self::NONCE_VALID
            || $valid === self::NONCE_INVALID
            || $valid === self::NONCE_EXPIRED
        ) {
            return $valid;
        }
        $result = $this->extractHash($nonce, $cacheKey);
        if (!is_array($result)) {
            return $this->cachedResult[$nonce][$action] = $result;
        }
        return $this->cachedResult[$nonce][$action] = self::NONCE_VALID;
    }
}
