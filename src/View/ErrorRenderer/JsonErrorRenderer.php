<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\ErrorRenderer;

use ArrayAccess\TrayDigita\Traits\Responder\JsonResponderFactoryTrait;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class JsonErrorRenderer extends AbstractErrorRenderer
{
    use JsonResponderFactoryTrait;

    protected function format(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ): ?string {
        $responder = $this->getJsonResponder();
        $message = $displayErrorDetails ? $exception : $this->getErrorDescription($exception);
        return $responder->encode($responder->format(500, $message, $displayErrorDetails));
    }
}
