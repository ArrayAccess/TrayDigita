<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\ApplicationChecker;

use ArrayAccess\TrayDigita\Console\Command\ApplicationCheck;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractChecker
{
    use TranslatorTrait;

    public function __construct(protected ApplicationCheck $applicationCheck)
    {
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->applicationCheck->getContainer();
    }

    public function getTranslator(): ?TranslatorInterface
    {
        return $this->applicationCheck->getTranslator();
    }

    public function getApplicationCheck(): ApplicationCheck
    {
        return $this->applicationCheck;
    }

    abstract public function check(InputInterface $input, OutputInterface $output) : int;
}
