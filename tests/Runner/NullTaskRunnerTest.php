<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        static::assertFalse($runner->support($task));
        static::assertTrue($runner->support(new NullTask('foo')));
    }

    public function testOutputIsReturned(): void
    {
        $task = new NullTask('test');

        $runner = new NullTaskRunner();
        $output = $runner->run($task);

        static::assertInstanceOf(Output::class, $output);
        static::assertNull($output->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}

final class BarTask extends AbstractTask
{
}
