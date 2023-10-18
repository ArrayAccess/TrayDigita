<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Helper;

use Symfony\Component\Finder\Finder;
use function ucwords;

abstract class AbstractLoaderNameBased extends AbstractHelper
{
    /**
     * @return ?Finder
     */
    abstract protected function getFileLists() : ?Finder;

    protected function doRegister(): bool
    {
        if (!$this->isProcessable()) {
            return false;
        }

        // preprocess
        $this->preProcess();
        $files = $this->getFileLists();
        if (!$files) {
            return false;
        }

        $mode = ucwords(trim($this->getMode()));
        $manager = $this->getManager();
        $mode && $manager?->dispatch(
            "kernel.beforeRegister$mode",
            $this->kernel
        );
        try {
            foreach ($files as $list) {
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
        return true;
    }
}
