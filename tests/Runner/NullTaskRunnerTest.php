<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Task\AbstractTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $task = new BarTask('test');

        $runner = new NullTaskRunner();

        self::assertFalse($runner->support($task));
        self::assertTrue($runner->support(new NullTask('foo')));
    }

    public function testOutputIsReturned(): void
    {
        $task = new NullTask('test');

        $runner = new NullTaskRunner();
        $output = $runner->run($task);

        self::assertInstanceOf(Output::class, $output);
        self::assertNull($output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}

final class BarTask extends AbstractTask
{
}
