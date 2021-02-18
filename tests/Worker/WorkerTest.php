<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\RateLimiterMiddleware;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\BlockingStoreInterface;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\Worker;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerTest extends TestCase
{
    public function testTaskCannotBeExecutedWithoutRunner(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker($scheduler, [], $watcher, new WorkerMiddlewareStack(), $eventDispatcher, $logger);

        self::expectException(UndefinedRunnerException::class);
        self::expectExceptionMessage('No runner found');
        self::expectExceptionCode(0);
        $worker->execute();
    }

    public function testWorkerCanBeConfigured(): void
    {
        $runner = $this->createMock(RunnerInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker($scheduler, [$runner], $watcher, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->stop();

        $worker->execute([
            'sleepDurationDelay' => 5,
        ]);

        self::assertNotNull($worker->getOptions());
        self::assertArrayHasKey('sleepDurationDelay', $worker->getOptions());
        self::assertSame(5, $worker->getOptions()['sleepDurationDelay']);
    }

    public function testTaskCannotBeExecutedWithoutSupportingRunner(): void
    {
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(false);
        $runner->expects(self::never())->method('run');

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::once())->method('support')->willReturn(false);
        $secondRunner->expects(self::never())->method('run');

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner, $secondRunner], $watcher, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNull($worker->getLastExecutedTask());
    }

    public function testTaskCannotBeExecutedWhileWorkerIsStopped(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch');

        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::never())->method('support');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::never())->method('getDueTasks');

        $worker = new Worker($scheduler, [$runner], $watcher, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->stop();
        $worker->execute();

        self::assertNull($worker->getLastExecutedTask());
    }

    public function testTaskCannotBeExecutedWhilePaused(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            self::equalTo('The following task "foo" is paused|disabled, consider enable it if it should be executed!'),
            [
                'name' => 'foo',
                'expression' => '* * * * *',
                'state' => TaskInterface::PAUSED,
            ]
        );

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(3))->method('getState')->willReturn(TaskInterface::PAUSED);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getName')->willReturn('bar');
        $secondTask->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::once())->method('isSingleRun')->willReturn(false);
        $secondTask->expects(self::once())->method('setArrivalTime');
        $secondTask->expects(self::once())->method('setExecutionStartTime');
        $secondTask->expects(self::once())->method('setExecutionEndTime');
        $secondTask->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($secondTask)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($secondTask)->willReturn(new Output($secondTask, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $secondTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($secondTask, $worker->getLastExecutedTask());
    }

    /**
     * @group time-sensitive
     */
    public function testTaskCannotBeExecutedWithAnExecutionDelay(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');
        $task->expects(self::exactly(2))->method('getExecutionDelay')->willReturn(1_000_000);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCannotBeExecutedWithErroredBeforeExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::never())->method('startTracking');
        $tracker->expects(self::never())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(4))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => false);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::never())->method('setArrivalTime');
        $task->expects(self::never())->method('setExecutionStartTime');
        $task->expects(self::never())->method('setExecutionEndTime');
        $task->expects(self::never())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::never())->method('run');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNotNull($worker->getLastExecutedTask());
        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithErroredBeforeExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(4))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => false);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::never())->method('setArrivalTime');
        $task->expects(self::never())->method('setExecutionStartTime');
        $task->expects(self::never())->method('setExecutionEndTime');
        $task->expects(self::never())->method('setLastExecution');

        $validTask = $this->createMock(TaskInterface::class);
        $validTask->expects(self::exactly(2))->method('getName')->willReturn('bar');
        $validTask->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $validTask->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => true);
        $validTask->expects(self::once())->method('getAfterExecuting')->willReturn(null);
        $validTask->expects(self::once())->method('isSingleRun')->willReturn(false);
        $validTask->expects(self::once())->method('setArrivalTime');
        $validTask->expects(self::once())->method('setExecutionStartTime');
        $validTask->expects(self::once())->method('setExecutionEndTime');
        $validTask->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::exactly(2))->method('support')->withConsecutive([$task], [$validTask])->willReturn(true);
        $runner->expects(self::once())->method('run')->with($validTask)->willReturn(new Output($validTask, null, Output::SUCCESS));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $validTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNotNull($worker->getLastExecutedTask());
        self::assertSame($validTask, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithBeforeExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => true);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithErroredAfterExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::exactly(2))->method('startTracking');
        $tracker->expects(self::exactly(2))->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(4))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $task->expects(self::exactly(2))->method('getAfterExecuting')->willReturn(fn (): bool => false);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $validTask = $this->createMock(TaskInterface::class);
        $validTask->expects(self::any())->method('getName')->willReturn('bar');
        $validTask->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $validTask->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $validTask->expects(self::exactly(2))->method('getAfterExecuting')->willReturn(fn (): bool => true);
        $validTask->expects(self::once())->method('isSingleRun')->willReturn(false);
        $validTask->expects(self::once())->method('setArrivalTime');
        $validTask->expects(self::once())->method('setExecutionStartTime');
        $validTask->expects(self::once())->method('setExecutionEndTime');
        $validTask->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::exactly(2))->method('support')->withConsecutive([$task], [$validTask])->willReturn(true);
        $runner->expects(self::exactly(2))->method('run')->withConsecutive([$task], [$validTask])->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $validTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(3));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNotEmpty($worker->getFailedTasks());
        self::assertSame($validTask, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithAfterExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $task->expects(self::exactly(2))->method('getAfterExecuting')->willReturn(fn (): bool => true);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertEmpty($worker->getFailedTasks());
        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithRunner(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedAndTheWorkerCanReturnTheLastExecutedTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCannotBeExecutedTwiceAsSingleRunTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $store = $this->createMock(BlockingStoreInterface::class);
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setExpression('* * * * *');
        $task->setSingleRun(true);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $worker = new Worker($scheduler, [$runner, $secondRunner], $tracker, new WorkerMiddlewareStack(), $eventDispatcher, $logger, $store);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testWorkerCanHandleFailedTask(): void
    {
        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->willThrowException(new RuntimeException('Random error occurred'));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::never())->method('endTracking');

        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('failed');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->execute();

        /** @var FailedTask $failedTask */
        $failedTask = $worker->getFailedTasks()->get('failed.failed');

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertNotEmpty($worker->getFailedTasks());
        self::assertCount(1, $worker->getFailedTasks());
        self::assertSame('Random error occurred', $failedTask->getReason());
    }

    public function testTaskCanBeExecutedWithoutBeforeExecutionNotificationAndNotifier(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $task->expects(self::once())->method('getBeforeExecutingNotificationBag')->willReturn(null);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher, $logger, null);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithBeforeExecutionNotificationAndNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = new Recipient('test@test.fr', '');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->with(self::equalTo($notification), $recipient);

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $task->expects(self::exactly(2))->method('getBeforeExecutingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher, $logger, null);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithoutAfterExecutionNotificationAndNotifier(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $task->expects(self::once())->method('getAfterExecutingNotificationBag')->willReturn(null);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher, $logger, null);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCanBeExecutedWithAfterExecutionNotificationAndNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = new Recipient('test@test.fr', '');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->with(self::equalTo($notification), $recipient);

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $task->expects(self::exactly(2))->method('getAfterExecutingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher, $logger, null);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testWorkerCannotReserveMaxExecutionTokensWithoutRateLimiter(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');
        $task->expects(self::never())->method('getMaxExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new RateLimiterMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testWorkerCannotReserveMaxExecutionTokensWithoutMaxExecutionLimit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');
        $task->expects(self::exactly(2))->method('getMaxExecution')->willReturn(null);

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($task));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new RateLimiterMiddleware(new RateLimiterFactory([
                'id' => 'foo',
                'policy' => 'token_bucket',
                'limit' => 1,
                'rate' => [
                    'interval' => '5 seconds',
                ],
            ], new InMemoryStorage())),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testWorkerCanReserveMaxExecutionTokensAndLimitTaskExecutionThenStopTheExecution(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');
        $task->expects(self::exactly(3))->method('getMaxExecution')->willReturn(1);

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($task));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new RateLimiterMiddleware(new RateLimiterFactory([
                'id' => 'foo',
                'policy' => 'token_bucket',
                'limit' => 1,
                'rate' => [
                    'interval' => '5 seconds',
                ],
            ], new InMemoryStorage())),
        ]), $eventDispatcher, $logger);

        $worker->execute();

        /** @var FailedTask $failedTask */
        $failedTask = $worker->getFailedTasks()->get('foo.failed');

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertSame($task, $failedTask->getTask());
        self::assertSame('Rate Limit Exceeded', $failedTask->getReason());
    }
}
