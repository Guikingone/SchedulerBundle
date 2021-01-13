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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\Runner\MessengerTaskRunner;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $runner = new MessengerTaskRunner();
        static::assertFalse($runner->support(new BarTask('test')));
        static::assertTrue($runner->support(new MessengerTask('foo', new FooMessage())));
    }

    public function testRunnerCanReturnOutputWithoutBus(): void
    {
        $runner = new MessengerTaskRunner();
        $task = new MessengerTask('foo', new FooMessage());

        $output = $runner->run($task);
        static::assertSame('The task cannot be handled as the bus is not defined', $output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnOutputWithBusAndException(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willThrowException(new \RuntimeException('An error occurred'));

        $runner = new MessengerTaskRunner($bus);
        $task = new MessengerTask('foo', new FooMessage());

        $output = $runner->run($task);

        static::assertSame('An error occurred', $output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnOutputWithBus(): void
    {
        $message = $this->createMock(FooMessage::class);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope($message));

        $runner = new MessengerTaskRunner($bus);
        $task = new MessengerTask('foo', $message);

        $output = $runner->run($task);

        static::assertNull($output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}

class FooMessage
{
}
