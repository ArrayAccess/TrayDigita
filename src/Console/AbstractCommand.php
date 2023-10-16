<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function sprintf;
use function strtolower;

abstract class AbstractCommand extends Command implements ContainerAllocatorInterface
{
    use ContainerAllocatorTrait;

    protected ?string $name = null;

    protected ?string $description = null;

    private array $reservedCommands = [
        'app:check',
        'app:db',
        'app:database',
        'app:generate:checksums',
        'generate-checksums',
        'app:generate:controller',
        'generate-controller',
        'app:generate:entity',
        'generate-entity',
        'app:generate:middleware',
        'generate-middleware',
        'app:generate:scheduler',
        'generate-scheduler',
        'app:generate:command',
        'generate-command',
        'app:generate:db-event',
        'app:generate:database-event',
        'generate-db-event',
        'generate-database-event',
        'app:generate:module',
        'generate-module',
        'app:server',
        'server',
    ];

    /**
     * @final
     */
    final public function __construct(Application $application)
    {
        parent::setApplication($application);

        $this->beforeConstruct();
        $this->name = trim($this->name??'');
        $this->description = trim($this->description??'');
        if (!$this->name) {
            $this->name = sprintf(
                'app:console:%s',
                strtolower(Consolidation::classShortName($this::class))
            );
        }

        $this->setName($this->name);
        parent::__construct($this->name);
        if (!$this->description) {
            $this->description = sprintf('Command for [<info>%s</info>]', $this->getName());
        }
        $this->setDescription($this->description);
    }

    /**
     * @param ?Application $application
     * @return void
     * no override
     */
    final public function setApplication(Application $application = null): void
    {
    }

    final public function setName(string $name): static
    {
        if (in_array($name, $this->reservedCommands)) {
            throw new UnsupportedArgumentException(
                sprintf(
                    'Command name %s has been reserved by system',
                    $name
                )
            );
        }

        return parent::setName($name);
    }

    protected function beforeConstruct()
    {
        // do
    }

    final protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        return $this->doExecute($input, $output);
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output) : int;
}
