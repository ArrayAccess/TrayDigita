<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Console\Command\ApplicationChecker\CacheChecker;
use ArrayAccess\TrayDigita\Console\Command\ApplicationChecker\ConfigChecker;
use ArrayAccess\TrayDigita\Console\Command\ApplicationChecker\ContainerChecker;
use ArrayAccess\TrayDigita\Console\Command\ApplicationChecker\DatabaseChecker;
use ArrayAccess\TrayDigita\Console\Command\ApplicationChecker\DependencyChecker;
use ArrayAccess\TrayDigita\Console\Command\ApplicationChecker\TranslatorChecker;
use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function is_object;
use function sprintf;

/**
 * @template AC of \ArrayAccess\TrayDigita\Console\Command\ApplicationChecker\AbstractChecker
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
class ApplicationCheck extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        WriterHelperTrait,
        TranslatorTrait;

    private ?EntityManager $entityManager = null;

    /**
     * @var array<class-string<AC>, AC>
     */
    protected array $checkers = [
        DependencyChecker::class => null,
        ConfigChecker::class => null,
        ContainerChecker::class => null,
        CacheChecker::class => null,
        TranslatorChecker::class => null,
        DatabaseChecker::class => null,
    ];

    protected function configure(): void
    {
        $this
            ->setName('app:check')
            ->setAliases([])
            ->setDescription(
                $this->translateContext('Check & validate application.', 'console')
            )
            ->setDefinition([])
            ->setHelp(
                sprintf(
                    $this->translateContext(
                        "The %s help you to validate application.\n"
                            . "This command show information about installed application services.",
                        'console'
                    ),
                    '<info>%command.name%</info>'
                )
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // no execute
        if ($output->isQuiet() || !Consolidation::isCli()) {
            return self::INVALID;
        }

        $manager = $this->getManager();
        $manager = $manager ?? ContainerHelper::service(ManagerInterface::class, $this->getContainer());
        if ($manager instanceof ManagerInterface) {
            $this->setManager($manager);
        }

        try {
            foreach ($this->checkers as $name => $checker) {
                if (is_object($checker)) {
                    continue;
                }
                $this->checkers[$name] = new $name($this);
            }

            $manager = $this->getManager();
            $output->writeln(sprintf(
                "<info>%s</info>",
                $this->translateContext('Application Check', 'console')
            ));
            $output->writeln('');

            // process
            foreach ($this->checkers as $checker) {
                $checker->check($input, $output);
            }

            // validation
            $manager?->dispatch(
                'console.applicationCheck',
                $this,
                $input,
                $output,
                $this
            );
            $output->writeln('');
        } finally {
            $this->printUsage($output);
        }
        return self::SUCCESS;
    }
}
