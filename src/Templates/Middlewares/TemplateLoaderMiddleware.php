<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Templates\Middlewares;

use ArrayAccess\TrayDigita\Middleware\AbstractMiddleware;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function is_file;
use const DIRECTORY_SEPARATOR;
use const PHP_INT_MAX;

class TemplateLoaderMiddleware extends AbstractMiddleware
{
    protected int $priority = PHP_INT_MAX - 10000;

    /**
     * @throws Throwable
     */
    protected function doProcess(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface
    {
        // register
        $active = ContainerHelper::use(ViewInterface::class, $this->getContainer())
            ?->getTemplateRule()
            ->getActive();
        if ($active) {
            // get template load
            $templateName = $active->getTemplateRule()->getTemplateLoad();
            if (!$templateName) {
                return $request;
            }
            $file = $active->getTemplateDirectory() . DIRECTORY_SEPARATOR . $templateName;
            if (is_file($file)) {
                try {
                    (fn($file) => include_once $file)->call($active, $file);
                    $this->getManager()->dispatch(
                        'templates.templateFileLoaded',
                        $active,
                        $file
                    );
                } catch (Throwable $e) {
                    $logger = ContainerHelper::use(
                        LoggerInterface::class,
                        $this->getContainer()
                    );
                    $logger->notice($e, context: ['mode' => 'templates_include']);
                    throw $e;
                }
            }
        }
        return $request;
    }
}
