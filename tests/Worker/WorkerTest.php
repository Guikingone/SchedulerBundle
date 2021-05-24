<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\MaxExecutionMiddleware;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\ChainedTaskRunner;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerTest extends TestCase
{
    /**
     * @throws Throwable
     */
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

    /**
     * @throws Throwable
     */
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
            'shouldStop' => true,
        ]);

        self::assertNotNull($worker->getOptions());
        self::assertArrayHasKey('sleepDurationDelay', $worker->getOptions());
        self::assertSame(5, $worker->getOptions()['sleepDurationDelay']);
    }

    /**
     * @throws Throwable
     */
    public function testTaskCannotBeExecutedWithoutSupportingRunner(): void
    {
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::never())->method('update');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with(self::equalTo($task))->willReturn(false);
        $runner->expects(self::never())->method('run');

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::once())->method('support')->willReturn(false);
        $secondRunner->expects(self::never())->method('run');

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner, $secondRunner], $watcher, new WorkerMiddlewareStack([
            new TaskUpdateMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNull($worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
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
        $scheduler->expects(self::never())->method('update');

        $worker = new Worker($scheduler, [$runner], $watcher, new WorkerMiddlewareStack([
            new TaskUpdateMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute([
            'shouldStop' => true,
        ]);

        self::assertNull($worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
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

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(3))->method('getState')->willReturn(TaskInterface::PAUSED);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(3))->method('getName')->willReturn('bar');
        $secondTask->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::once())->method('isSingleRun')->willReturn(false);
        $secondTask->expects(self::once())->method('setArrivalTime');
        $secondTask->expects(self::once())->method('setExecutionStartTime');
        $secondTask->expects(self::once())->method('setExecutionEndTime');
        $secondTask->expects(self::once())->method('setLastExecution');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($secondTask));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($secondTask));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($secondTask)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($secondTask)->willReturn(new Output($secondTask, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $secondTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($secondTask, $worker->getLastExecutedTask());
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCannotBeExecutedWithErroredBeforeExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(4))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => false);
        $task->expects(self::never())->method('isSingleRun');
        $task->expects(self::never())->method('setArrivalTime');
        $task->expects(self::never())->method('setExecutionStartTime');
        $task->expects(self::never())->method('setExecutionEndTime');
        $task->expects(self::never())->method('setLastExecution');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::never())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::never())->method('endTracking')->with(self::equalTo($task));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with(self::equalTo($task))->willReturn(true);
        $runner->expects(self::never())->method('run');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNotNull($worker->getLastExecutedTask());
        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCanBeExecutedWithErroredBeforeExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(4))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => false);
        $task->expects(self::never())->method('isSingleRun');
        $task->expects(self::never())->method('setArrivalTime');
        $task->expects(self::never())->method('setExecutionStartTime');
        $task->expects(self::never())->method('setExecutionEndTime');
        $task->expects(self::never())->method('setLastExecution');

        $validTask = $this->createMock(TaskInterface::class);
        $validTask->expects(self::exactly(2))->method('getName')->willReturn('bar');
        $validTask->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $validTask->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => true);
        $validTask->expects(self::once())->method('getAfterExecuting')->willReturn(null);
        $validTask->expects(self::once())->method('isSingleRun')->willReturn(false);
        $validTask->expects(self::once())->method('setArrivalTime');
        $validTask->expects(self::once())->method('setExecutionStartTime');
        $validTask->expects(self::once())->method('setExecutionEndTime');
        $validTask->expects(self::once())->method('setLastExecution');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($validTask));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($validTask));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::exactly(2))->method('support')->withConsecutive([$task], [$validTask])->willReturn(true);
        $runner->expects(self::once())->method('run')->with($validTask)->willReturn(new Output($validTask, null, Output::SUCCESS));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $validTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNotNull($worker->getLastExecutedTask());
        self::assertSame($validTask, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCanBeExecutedWithBeforeExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => true);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

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
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCanBeExecutedWithErroredAfterExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

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
        $validTask->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $validTask->expects(self::once())->method('getBeforeExecuting')->willReturn(null);
        $validTask->expects(self::exactly(2))->method('getAfterExecuting')->willReturn(fn (): bool => true);
        $validTask->expects(self::once())->method('isSingleRun')->willReturn(false);
        $validTask->expects(self::once())->method('setArrivalTime');
        $validTask->expects(self::once())->method('setExecutionStartTime');
        $validTask->expects(self::once())->method('setExecutionEndTime');
        $validTask->expects(self::once())->method('setLastExecution');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::exactly(2))->method('startTracking')->withConsecutive([$task], [$validTask]);
        $tracker->expects(self::exactly(2))->method('endTracking')->withConsecutive([$task], [$validTask]);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::exactly(2))->method('support')
            ->withConsecutive([$task], [$validTask])
            ->willReturn(true)
        ;
        $runner->expects(self::exactly(2))->method('run')
            ->withConsecutive([$task], [$validTask])
            ->willReturn(new Output($task, null))
        ;

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $validTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(3));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertCount(1, $worker->getFailedTasks());
        self::assertSame($validTask, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCanBeExecutedWithAfterExecutionCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertCount(0, $worker->getFailedTasks());
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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
            new SingleRunTaskMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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
            new SingleRunTaskMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testTaskCannotBeExecutedTwiceAsSingleRunTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->setExpression('* * * * *');
        $shellTask->setSingleRun(true);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($shellTask)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($shellTask)->willReturn(new Output($shellTask, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(new Output($shellTask, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$shellTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $worker = new Worker($scheduler, [$runner, $secondRunner], $tracker, new WorkerMiddlewareStack(), $eventDispatcher, $logger);
        $worker->execute();

        self::assertSame($shellTask, $worker->getLastExecutedTask());
    }

    public function testWorkerCanHandleFailedTask(): void
    {
        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->willThrowException(new RuntimeException('Random error occurred'));

        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('failed');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::never())->method('endTracking');

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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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
            new SingleRunTaskMiddleware($scheduler),
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher, $logger);
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher, $logger);
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
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
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher, $logger);
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');
        $task->expects(self::never())->method('getMaxExecutions');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new MaxExecutionMiddleware(),
            new SingleRunTaskMiddleware($scheduler),
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');
        $task->expects(self::exactly(2))->method('getMaxExecutions')->willReturn(null);

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
            new MaxExecutionMiddleware(new RateLimiterFactory([
                'id' => 'foo',
                'policy' => 'token_bucket',
                'limit' => 1,
                'rate' => [
                    'interval' => '5 seconds',
                ],
            ], new InMemoryStorage())),
            new SingleRunTaskMiddleware($scheduler),
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
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');
        $task->expects(self::exactly(3))->method('getMaxExecutions')->willReturn(1);

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
            new MaxExecutionMiddleware(new RateLimiterFactory([
                'id' => 'foo',
                'policy' => 'token_bucket',
                'limit' => 1,
                'rate' => [
                    'interval' => '5 seconds',
                ],
            ], new InMemoryStorage())),
            new SingleRunTaskMiddleware($scheduler),
        ]), $eventDispatcher, $logger);

        $worker->execute();

        /** @var FailedTask $failedTask */
        $failedTask = $worker->getFailedTasks()->get('foo.failed');

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertSame($task, $failedTask->getTask());
        self::assertSame('Rate Limit Exceeded', $failedTask->getReason());
    }

    public function testWorkerCanStopWhenTaskAreConsumedAndWithoutDaemonEnabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($task));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(7))->method('dispatch');

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute([], $task);

        self::assertCount(0, $worker->getFailedTasks());
        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testWorkerCanStopWhenTaskAreConsumedWithError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(4))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::never())->method('isSingleRun');
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::never())->method('setExecutionEndTime');
        $task->expects(self::never())->method('setLastExecution');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::never())->method('endTracking');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willThrowException(new RuntimeException('An error occurred'));

        $scheduler = $this->createMock(SchedulerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(7))->method('dispatch');

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute([], $task);

        self::assertNotEmpty($worker->getFailedTasks());
        self::assertSame($task, $worker->getLastExecutedTask());
    }

    public function testPausedTaskIsNotExecutedIfListContainsASingleTask(): void
    {
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $runner = $this->createMock(RunnerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')->withConsecutive([
            self::equalTo('The following task "bar" is paused|disabled, consider enable it if it should be executed!'),
            [
                'name' => 'bar',
                'expression' => '* * * * *',
                'state' => TaskInterface::PAUSED,
            ],
        ], [
            self::equalTo('The following task "foo" is paused|disabled, consider enable it if it should be executed!'),
            [
                'name' => 'foo',
                'expression' => '* * * * *',
                'state' => TaskInterface::PAUSED,
            ],
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(3))->method('getState')->willReturn(TaskInterface::PAUSED);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(3))->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $secondTask->expects(self::exactly(3))->method('getState')->willReturn(TaskInterface::PAUSED);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$secondTask, $task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
        ]), $eventDispatcher, $logger);
        $worker->execute();

        self::assertNull($worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCanExecuteChainedTasks(): void
    {
        $chainedTask = new ChainedTask(
            'foo',
            new ShellTask('chained_foo', ['ls', '-al']),
            new ShellTask('chained_bar', ['ls', '-al'])
        );
        $shellTask = new ShellTask('bar', ['ls', '-al']);

        $logger = $this->createMock(LoggerInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$chainedTask, $shellTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(4));

        $worker = new Worker(
            $scheduler,
            [
                new ChainedTaskRunner(),
                new ShellTaskRunner(),
            ],
            new TaskExecutionTracker(new Stopwatch()),
            new WorkerMiddlewareStack([
                new SingleRunTaskMiddleware($scheduler),
                new TaskUpdateMiddleware($scheduler),
            ]),
            $eventDispatcher,
            $logger
        );
        $worker->execute();

        self::assertSame($shellTask, $worker->getLastExecutedTask());
        self::assertSame(TaskInterface::SUCCEED, $chainedTask->getExecutionState());
        self::assertNotNull($chainedTask->getExecutionStartTime());
        self::assertNotNull($chainedTask->getExecutionEndTime());
        self::assertSame(TaskInterface::SUCCEED, $chainedTask->getTask('chained_foo')->getExecutionState());
        self::assertNotNull($chainedTask->getTask('chained_foo')->getExecutionStartTime());
        self::assertNotNull($chainedTask->getTask('chained_foo')->getExecutionEndTime());
        self::assertSame(TaskInterface::SUCCEED, $chainedTask->getTask('chained_bar')->getExecutionState());
        self::assertNotNull($chainedTask->getTask('chained_bar')->getExecutionStartTime());
        self::assertNotNull($chainedTask->getTask('chained_bar')->getExecutionEndTime());
        self::assertSame(TaskInterface::SUCCEED, $shellTask->getExecutionState());
        self::assertNotNull($shellTask->getExecutionStartTime());
        self::assertNotNull($shellTask->getExecutionEndTime());
    }
}
