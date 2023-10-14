<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Templates\Abstracts\AbstractTemplateRule;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

interface ViewInterface extends ContainerIndicateInterface, ManagerIndicateInterface
{
    public function hasEngine(string $extension) : bool;

    public function removeEngine(string $extension);

    public function addEngine(ViewEngineInterface $engine, ?string $extension = null);

    public function getManager(): ManagerInterface;

    /**
     * @return array{string: ViewEngineInterface}
     */
    public function getEngines() : array;

    /**
     * @param string $extension
     * @return ?ViewEngineInterface
     */
    public function getEngine(string $extension) : ?ViewEngineInterface;

    public function clearVariableCache();

    /**
     * @return array<string>
     */
    public function getViewsDirectory() : array;

    public function setViewsDirectory(string|iterable $dir);

    public function appendViewsDirectory(string $dir);

    public function prependViewsDirectory(string $dir);

    public function getTemplateRule(): ?AbstractTemplateRule;

    public function setTemplateRule(?AbstractTemplateRule $templateRule);

    public function exist(string $path) : bool;

    public function render(string $path, array $parameters = []) : string;

    /**
     * @param string $path
     * @param array $parameters
     * @param ?ResponseInterface $response
     * @return ResponseInterface
     * @uses render()
     */
    public function serve(
        string $path,
        array $parameters = [],
        ?ResponseInterface $response = null
    ) : ResponseInterface;

    public function getParameters() : array;
    public function getParameter($name);
    public function removeParameter($name);
    public function setParameters(array $parameters);
    public function setParameter($name, $value);
    public function hasParameter($name) : bool;

    public function getRequest() : ServerRequestInterface;

    public function setRequest(ServerRequestInterface $request);

    public function getBaseURI(
        string $path = '',
        RequestInterface|UriInterface|null $requestOrUri = null
    ): UriInterface;

    public function getTemplateURI(
        string $path = '',
        RequestInterface|UriInterface|null $requestOrUri = null
    ): UriInterface;

    public function dispatchHeader();

    public function getDispatcherHeaderCount() : int;

    public function dispatchFooter();

    public function getDispatcherFooterCount() : int;
}
