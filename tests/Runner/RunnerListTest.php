<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\Runner\RunnerList;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RunnerListTest extends TestCase
{
    public function testListCanFilterRunnerList(): void
    {
        $runnerList = new RunnerList([
            new CallbackTaskRunner(),
            new ShellTaskRunner(),
            new NullTaskRunner(),
        ]);

        self::assertCount(2, $runnerList);

        $filteredList = $runnerList->filter(fn(RunnerInterface $runner): bool => $runner->support(new NullTask('foo')));
        self::assertCount(1, $filteredList);
    }
}
