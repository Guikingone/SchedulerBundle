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
        $messengerTaskRunner = new MessengerTaskRunner();
        self::assertFalse($messengerTaskRunner->support(new BarTask('test')));
        self::assertTrue($messengerTaskRunner->support(new MessengerTask('foo', new stdClass())));
    }

    public function testRunnerCannotExecuteInvalidTask(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);

        $messengerTaskRunner = new MessengerTaskRunner();
        $output = $messengerTaskRunner->run($shellTask);

        self::assertSame(TaskInterface::ERRORED, $shellTask->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
        self::assertSame($shellTask, $output->getTask());
    }

    public function testRunnerCanReturnOutputWithoutBus(): void
    {
        $messengerTaskRunner = new MessengerTaskRunner();
        $messengerTask = new MessengerTask('foo', new stdClass());

        $output = $messengerTaskRunner->run($messengerTask);
        self::assertSame('The task cannot be handled as the bus is not defined', $output->getOutput());
        self::assertSame($messengerTask, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnOutputWithBusAndException(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willThrowException(new RuntimeException('An error occurred'));

        $messengerTaskRunner = new MessengerTaskRunner($bus);
        $messengerTask = new MessengerTask('foo', new stdClass());

        $output = $messengerTaskRunner->run($messengerTask);

        self::assertSame('An error occurred', $output->getOutput());
        self::assertSame($messengerTask, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnOutputWithBus(): void
    {
        $message = $this->createMock(stdClass::class);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope($message));

        $messengerTaskRunner = new MessengerTaskRunner($bus);
        $messengerTask = new MessengerTask('foo', $message);

        $output = $messengerTaskRunner->run($messengerTask);

        self::assertNull($output->getOutput());
        self::assertSame($messengerTask, $output->getTask());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}
