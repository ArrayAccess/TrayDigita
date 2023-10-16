<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use Symfony\Component\Finder\Finder;
use function ucwords;

abstract class AbstractLoaderNameBased extends AbstractHelper
{
    /**
     * @return Finder
     */
    abstract protected function getFileLists() : Finder;

    protected function doRegister(): void
    {
        if (!$this->isProcessable()) {
            return;
        }

        // preprocess
        $this->preProcess();

        $mode = ucwords(trim($this->getMode()));
        $manager = $this->getManager();
        $mode && $manager?->dispatch(
            "kernel.beforeRegister$mode",
            $this->kernel
        );
        try {
            foreach ($this->getFileLists() as $list) {
                $this->loadService($list);
            }

            $mode && $manager?->dispatch(
                "kernel.register$mode",
                $this->kernel
            );
        } finally {
            $mode && $manager?->dispatch(
                "kernel.afterRegister$mode",
                $this->kernel
            );

            // postprocess
            $this->postProcess();
        }
    }
}
