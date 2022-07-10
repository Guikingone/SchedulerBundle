<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractWorkerTestCase extends TestCase
{
    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    abstract protected function getWorker(): WorkerInterface;

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testTaskCannotBeExecutedWithoutRunner(): void
    {
        $worker = $this->getWorker();

        self::expectException(UndefinedRunnerException::class);
        self::expectExceptionMessage('No runner found');
        self::expectExceptionCode(0);
        $worker->execute(WorkerConfiguration::create());
    }
}
