<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use Tests\SchedulerBundle\Runner\Assets\BarTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $barTask = new BarTask('test');

        $nullTaskRunner = new NullTaskRunner();

        self::assertFalse($nullTaskRunner->support($barTask));
        self::assertTrue($nullTaskRunner->support(new NullTask('foo')));
    }

    public function testOutputIsReturned(): void
    {
        $nullTask = new NullTask('test');

        $nullTaskRunner = new NullTaskRunner();
        $output = $nullTaskRunner->run($nullTask);

        self::assertNull($output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}
