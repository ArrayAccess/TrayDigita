<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Generator\Interfaces;

use Psr\Http\Message\ServerRequestInterface;

interface NonceRequestInterface extends NonceInterface
{
    /**
     * @inheritdoc
     *
     * @param string $action
     * @param ServerRequestInterface|null $request
     * @return ?string
     */
    public function generate(string $action, ?ServerRequestInterface $request = null): ?string;

    /**
     * @inheritdoc
     *
     * @param string $nonce
     * @param string $action
     * @param ServerRequestInterface|null $request
     * @return bool|int
     */
    public function validate(string $nonce, string $action, ?ServerRequestInterface $request = null): bool|int;

    /**
     * Set request for nonce
     *
     * @param ServerRequestInterface $request
     */
    public function setRequest(ServerRequestInterface $request);

    /**
     * Getting global request
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface;

    /**
     * Create request hash method from request
     *
     * @param string $action
     * @param ?ServerRequestInterface $request if null use self::getRequest()
     * @return string
     */
    public function createRequestHash(
        string $action,
        ?ServerRequestInterface $request = null
    ): string;
}
