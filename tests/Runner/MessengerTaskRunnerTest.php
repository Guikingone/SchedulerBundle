<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\Runner\MessengerTaskRunner;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\TaskInterface;
use Tests\SchedulerBundle\Runner\Assets\BarTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $runner = new MessengerTaskRunner();
        self::assertFalse($runner->support(new BarTask('test')));
        self::assertTrue($runner->support(new MessengerTask('foo', new stdClass())));
    }

    public function testRunnerCannotExecuteInvalidTask(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony']);

        $runner = new MessengerTaskRunner();
        $output = $runner->run($task);

        self::assertSame(TaskInterface::ERRORED, $task->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
        self::assertSame($task, $output->getTask());
    }

    public function testRunnerCanReturnOutputWithoutBus(): void
    {
        $runner = new MessengerTaskRunner();
        $task = new MessengerTask('foo', new stdClass());

        $output = $runner->run($task);
        self::assertSame('The task cannot be handled as the bus is not defined', $output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnOutputWithBusAndException(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willThrowException(new RuntimeException('An error occurred'));

        $runner = new MessengerTaskRunner($bus);
        $task = new MessengerTask('foo', new stdClass());

        $output = $runner->run($task);

        self::assertSame('An error occurred', $output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnOutputWithBus(): void
    {
        $message = $this->createMock(stdClass::class);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope($message));

        $runner = new MessengerTaskRunner($bus);
        $task = new MessengerTask('foo', $message);

        $output = $runner->run($task);

        self::assertNull($output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}
