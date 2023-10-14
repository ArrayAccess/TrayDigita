<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Templates\Interfaces;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Templates\Abstracts\AbstractTemplateRule;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

interface TemplateInterface extends ContainerIndicateInterface, ManagerIndicateInterface
{
    public function getTemplateRule() : AbstractTemplateRule;

    public function getView() : ViewInterface;

    public function getName() : string;

    public function getVersion() : ?string;

    public function getDescription() : ?string;

    public function getDirectory() : string;

    public function getBasePath() : string;

    public function valid() : bool;

    public function getTextDomain(): string;

    public function getBaseURI(
        string $path = '',
        RequestInterface|UriInterface|null $requestOrUri = null
    ) : UriInterface;
}
