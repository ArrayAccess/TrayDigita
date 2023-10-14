<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Interfaces;

use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\LastRecord;
use ArrayAccess\TrayDigita\Scheduler\Runner;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;

interface RecordLoaderInterface
{
    public function getRecord(Task $task) : ?LastRecord;

    public function storeExitRunner(Runner $runner, Scheduler $scheduler) : ?LastRecord;

    public function doStartProgress(Runner $runner, Scheduler $scheduler) : ?LastRecord;

    public function doSkipProgress(Runner $runner, Scheduler $scheduler) : ?LastRecord;

    public function finish(
        int $executionTime,
        Runner $runner,
        Scheduler $scheduler
    );
}
