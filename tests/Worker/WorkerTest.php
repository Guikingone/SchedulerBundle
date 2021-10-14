<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use SchedulerBundle\Event\WorkerPausedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\MaxExecutionMiddleware;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\TaskExecutionMiddleware;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Runner\ChainedTaskRunner;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Runner\CommandTaskRunner;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Application;
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
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Stopwatch\Stopwatch;
use Tests\SchedulerBundle\Worker\Assets\LongExecutionCommand;
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

        $worker = new Worker($scheduler, new RunnerRegistry([]), $watcher, new WorkerMiddlewareStack(), $eventDispatcher, new LockFactory(new InMemoryStore()), $logger);

        self::expectException(UndefinedRunnerException::class);
        self::expectExceptionMessage('No runner found');
        self::expectExceptionCode(0);
        $worker->execute(WorkerConfiguration::create());
    }

    /**
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function testWorkerCanBeConfigured(): void
    {
        $runner = $this->createMock(RunnerInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $watcher, new WorkerMiddlewareStack([
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        $configuration = WorkerConfiguration::create();
        $configuration->stop();

        $worker->execute($configuration);

        self::assertSame(0, $worker->getConfiguration()->getExecutedTasksCount());
        self::assertNull($worker->getConfiguration()->getForkedFrom());
        self::assertFalse($worker->getConfiguration()->isFork());
        self::assertFalse($worker->getConfiguration()->isRunning());
        self::assertNull($worker->getConfiguration()->getLastExecutedTask());
        self::assertSame(1, $worker->getConfiguration()->getSleepDurationDelay());
        self::assertFalse($worker->getConfiguration()->isSleepingUntilNextMinute());
        self::assertTrue($worker->getConfiguration()->shouldStop());
        self::assertFalse($worker->getConfiguration()->shouldRetrieveTasksLazily());
        self::assertFalse($worker->getConfiguration()->isStrictlyCheckingDate());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCanBeForked(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(3))->method('dispatch');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $watcher, new WorkerMiddlewareStack([
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, new NullLogger());

        $configuration = WorkerConfiguration::create();
        $configuration->setSleepDurationDelay(5);

        $worker->execute($configuration);
        $forkedWorker = $worker->fork();

        self::assertNotSame($forkedWorker, $worker);
        self::assertSame(0, $forkedWorker->getConfiguration()->getExecutedTasksCount());
        self::assertNotNull($forkedWorker->getConfiguration()->getForkedFrom());
        self::assertSame($worker, $forkedWorker->getConfiguration()->getForkedFrom());
        self::assertTrue($forkedWorker->getConfiguration()->isFork());
        self::assertFalse($forkedWorker->getConfiguration()->isRunning());
        self::assertNull($forkedWorker->getConfiguration()->getLastExecutedTask());
        self::assertSame(1, $forkedWorker->getConfiguration()->getSleepDurationDelay());
        self::assertFalse($forkedWorker->getConfiguration()->isSleepingUntilNextMinute());
        self::assertFalse($forkedWorker->getConfiguration()->shouldStop());
        self::assertFalse($forkedWorker->getConfiguration()->shouldRetrieveTasksLazily());
        self::assertFalse($worker->getConfiguration()->isStrictlyCheckingDate());
    }

    /**
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function testTaskCannotBeExecutedWithoutSupportingRunner(): void
    {
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::never())->method('update');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(5))->method('dispatch');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new CallbackTaskRunner(),
            new ShellTaskRunner(),
        ]), $watcher, new WorkerMiddlewareStack([
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertNull($worker->getLastExecutedTask());
        self::assertCount(1, $worker->getFailedTasks());

        $failedTask = $worker->getFailedTasks()->get('foo.failed');
        self::assertInstanceOf(FailedTask::class, $failedTask);

        $task = $failedTask->getTask();
        self::assertSame('foo', $task->getName());
        self::assertNull($task->getExecutionState());
        self::assertNull($task->getLastExecution());
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

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::never())->method('getDueTasks');
        $scheduler->expects(self::never())->method('update');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $watcher, new WorkerMiddlewareStack([
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        $configuration = WorkerConfiguration::create();
        $configuration->stop();

        $worker->execute($configuration);

        self::assertNull($worker->getLastExecutedTask());
        self::assertTrue($worker->getConfiguration()->shouldStop());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCannotBeExecutedWhilePaused(): void
    {
        $task = new NullTask('foo', [
            'state' => TaskInterface::PAUSED,
        ]);

        $secondTask = new NullTask('bar');

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
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($secondTask));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($secondTask));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $secondTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertNull($task->getExecutionState());
        self::assertSame(TaskInterface::SUCCEED, $secondTask->getExecutionState());
        self::assertSame($secondTask, $worker->getLastExecutedTask());
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable
     */
    public function testTaskCannotBeExecutedWithAnExecutionDelay(): void
    {
        $task = new NullTask('foo', [
            'execution_delay' => 1_000_000,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCannotBeExecutedWithErroredBeforeExecutionCallback(): void
    {
        $task = new NullTask('foo', [
            'before_executing' => fn (): bool => false,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::never())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::never())->method('endTracking')->with(self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertNull($worker->getLastExecutedTask());
        self::assertCount(1, $worker->getFailedTasks());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedWithErroredBeforeExecutionCallback(): void
    {
        $task = new NullTask('foo', [
            'before_executing' => fn (): bool => false,
        ]);

        $validTask = new NullTask('bar', [
            'before_executing' => fn (): bool => true,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($validTask));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($validTask));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $validTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertCount(1, $worker->getFailedTasks());
        self::assertInstanceOf(FailedTask::class, $worker->getFailedTasks()->get('foo.failed'));
        self::assertNotNull($worker->getLastExecutedTask());
        self::assertSame($validTask, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedWithBeforeExecutionCallback(): void
    {
        $task = new NullTask('foo', [
            'before_executing' => fn (): bool => true,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertCount(0, $worker->getFailedTasks());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedWithErroredAfterExecutionCallback(): void
    {
        $task = new NullTask('foo', [
            'after_executing' => fn (): bool => false,
        ]);

        $validTask = new NullTask('bar', [
            'after_executing' => fn (): bool => true,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::exactly(2))->method('startTracking')->withConsecutive([$task], [$validTask]);
        $tracker->expects(self::exactly(2))->method('endTracking')->withConsecutive([$task], [$validTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $validTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(3));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertCount(1, $worker->getFailedTasks());
        self::assertInstanceOf(FailedTask::class, $worker->getFailedTasks()->get('foo.failed'));
        self::assertNotNull($worker->getLastExecutedTask());
        self::assertSame($validTask, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedWithAfterExecutionCallback(): void
    {
        $task = new NullTask('foo', [
            'after_executing' => fn (): bool => true,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertCount(0, $worker->getFailedTasks());
        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedWithRunner(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskLockBagMiddleware(new LockFactory(new InMemoryStore())),
        ]), $eventDispatcher);
        $scheduler->schedule(new NullTask('foo'));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker(
            $scheduler,
            new RunnerRegistry([
                new NullTaskRunner(),
            ]),
            new TaskExecutionTracker(new Stopwatch()),
            new WorkerMiddlewareStack([
                new TaskCallbackMiddleware(),
                new NotifierMiddleware(),
                new SingleRunTaskMiddleware($scheduler),
                new TaskUpdateMiddleware($scheduler),
                new TaskLockBagMiddleware($lockFactory),
            ]),
            $eventDispatcher,
            $lockFactory,
            $logger
        );

        $worker->execute(WorkerConfiguration::create());
        self::assertInstanceOf(NullTask::class, $worker->getLastExecutedTask());

        $task = $scheduler->getTasks()->get('foo');

        self::assertFalse($task->isSingleRun());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getArrivalTime());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getExecutionEndTime());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getLastExecution());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedAndTheWorkerCanReturnTheLastExecutedTask(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
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
        $secondRunner->expects(self::once())->method('support')->willReturn(false);
        $secondRunner->expects(self::never())->method('run')->willReturn(new Output($shellTask, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$shellTask]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            $runner,
            $secondRunner,
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($shellTask, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testWorkerCanHandleFailedTask(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->willThrowException(new RuntimeException('Random error occurred'));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::never())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            $runner,
        ]), $tracker, new WorkerMiddlewareStack([
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        $failedTasks = $worker->getFailedTasks();
        self::assertCount(1, $failedTasks);

        $failedTask = $failedTasks->get('foo.failed');
        self::assertInstanceOf(FailedTask::class, $failedTask);
        self::assertNotEmpty($worker->getFailedTasks());
        self::assertCount(1, $worker->getFailedTasks());
        self::assertSame('Random error occurred', $failedTask->getReason());
        self::assertNull($worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedWithoutBeforeExecutionNotificationAndNotifier(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertCount(0, $worker->getFailedTasks());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testTaskCanBeExecutedWithBeforeExecutionNotificationAndNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = new Recipient('test@test.fr', '');

        $task = new NullTask('foo', [
            'before_executing_notification' => new NotificationTaskBag($notification, $recipient),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->with(self::equalTo($notification), $recipient);

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertCount(0, $worker->getFailedTasks());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCanBeExecutedWithoutAfterExecutionNotificationAndNotifier(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertCount(0, $worker->getFailedTasks());
    }

    /**
     * @throws Throwable
     */
    public function testTaskCanBeExecutedWithAfterExecutionNotificationAndNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = new Recipient('test@test.fr', '');

        $task = new NullTask('foo', [
            'after_executing_notification' => new NotificationTaskBag($notification, $recipient),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The lock for task "foo" has been released'));

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->with(self::equalTo($notification), $recipient);

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
            new TaskLockBagMiddleware($lockFactory, $logger),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertCount(0, $worker->getFailedTasks());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCannotReserveMaxExecutionTokensWithoutRateLimiter(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new MaxExecutionMiddleware(),
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCannotReserveMaxExecutionTokensWithoutMaxExecutionLimit(): void
    {
        $task = new NullTask('foo', [
            'max_executions' => null,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new MaxExecutionMiddleware(new RateLimiterFactory([
                'id' => 'foo',
                'policy' => 'token_bucket',
                'limit' => 1,
                'rate' => [
                    'interval' => '5 seconds',
                ],
            ], new InMemoryStorage())),
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCanReserveMaxExecutionTokensAndLimitTaskExecutionThenStopTheExecution(): void
    {
        $task = new NullTask('foo', [
            'max_executions' => 1,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new MaxExecutionMiddleware(new RateLimiterFactory([
                'id' => 'foo',
                'policy' => 'token_bucket',
                'limit' => 1,
                'rate' => [
                    'interval' => '5 seconds',
                ],
            ], new InMemoryStorage())),
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create());

        self::assertNull($worker->getLastExecutedTask());
        self::assertCount(1, $worker->getFailedTasks());

        $failedTask = $worker->getFailedTasks()->get('foo.failed');
        self::assertInstanceOf(FailedTask::class, $failedTask);
        self::assertSame($task, $failedTask->getTask());
        self::assertSame('Rate Limit Exceeded', $failedTask->getReason());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCanStopWhenTaskAreConsumedAndWithoutDaemonEnabled(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::once())->method('endTracking')->with(self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(7))->method('dispatch');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create(), $task);

        self::assertCount(0, $worker->getFailedTasks());
        self::assertSame($task, $worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCanStopWhenTaskAreConsumedWithError(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking')->with(self::equalTo($task));
        $tracker->expects(self::never())->method('endTracking');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willThrowException(new RuntimeException('An error occurred'));

        $scheduler = $this->createMock(SchedulerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(7))->method('dispatch');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create(), $task);

        self::assertCount(1, $worker->getFailedTasks());
        self::assertNull($worker->getLastExecutedTask());
    }

    /**
     * @throws Throwable
     */
    public function testPausedTaskIsNotExecutedIfListContainsASingleTask(): void
    {
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

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

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList(
            [
                new NullTask('bar', [
                    'access_lock_bag' => new AccessLockBag(new Key('bar')),
                    'state' => TaskInterface::PAUSED,
                ]),
                new NullTask('foo', [
                    'access_lock_bag' => new AccessLockBag(new Key('foo')),
                    'state' => TaskInterface::PAUSED,
                ]),
            ]
        ));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);
        $worker->execute(WorkerConfiguration::create());

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
        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker(
            $scheduler,
            new RunnerRegistry([
                new ChainedTaskRunner(),
                new ShellTaskRunner(),
            ]),
            new TaskExecutionTracker(new Stopwatch()),
            new WorkerMiddlewareStack([
                new SingleRunTaskMiddleware($scheduler),
                new TaskUpdateMiddleware($scheduler),
                new TaskLockBagMiddleware($lockFactory),
            ]),
            $eventDispatcher,
            $lockFactory,
            $logger
        );
        $worker->execute(WorkerConfiguration::create());

        self::assertSame($shellTask, $worker->getLastExecutedTask());
        self::assertSame(TaskInterface::SUCCEED, $chainedTask->getExecutionState());
        self::assertNotNull($chainedTask->getExecutionStartTime());
        self::assertNotNull($chainedTask->getExecutionEndTime());

        $chainedFooTask = $chainedTask->getTask('chained_foo');
        self::assertInstanceOf(ShellTask::class, $chainedFooTask);
        self::assertSame(TaskInterface::SUCCEED, $chainedFooTask->getExecutionState());
        self::assertNotNull($chainedFooTask->getExecutionStartTime());
        self::assertNotNull($chainedFooTask->getExecutionEndTime());

        $chainedBarTask = $chainedTask->getTask('chained_bar');
        self::assertInstanceOf(ShellTask::class, $chainedBarTask);
        self::assertSame(TaskInterface::SUCCEED, $chainedBarTask->getExecutionState());
        self::assertNotNull($chainedBarTask->getExecutionStartTime());
        self::assertNotNull($chainedBarTask->getExecutionEndTime());

        self::assertSame(TaskInterface::SUCCEED, $shellTask->getExecutionState());
        self::assertNotNull($shellTask->getExecutionStartTime());
        self::assertNotNull($shellTask->getExecutionEndTime());
    }

    /**
     * @throws Throwable
     */
    public function testWorkerCanRetrieveTasksLazily(): void
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
        $scheduler->expects(self::once())
            ->method('getDueTasks')
            ->with(self::equalTo(true))
            ->willReturn(new LazyTaskList(new TaskList([$chainedTask, $shellTask])))
        ;

        $eventDispatcher = new EventDispatcher();
        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker(
            $scheduler,
            new RunnerRegistry([
                new ChainedTaskRunner(),
                new ShellTaskRunner(),
            ]),
            new TaskExecutionTracker(new Stopwatch()),
            new WorkerMiddlewareStack([
                new SingleRunTaskMiddleware($scheduler),
                new TaskUpdateMiddleware($scheduler),
                new TaskLockBagMiddleware($lockFactory),
            ]),
            $eventDispatcher,
            $lockFactory,
            $logger
        );

        $configuration = WorkerConfiguration::create();
        $configuration->mustRetrieveTasksLazily(true);

        $worker->execute($configuration);

        self::assertSame($shellTask, $worker->getLastExecutedTask());
        self::assertSame(TaskInterface::SUCCEED, $chainedTask->getExecutionState());
        self::assertNotNull($chainedTask->getExecutionStartTime());
        self::assertNotNull($chainedTask->getExecutionEndTime());

        $chainedFooTask = $chainedTask->getTask('chained_foo');
        self::assertInstanceOf(ShellTask::class, $chainedFooTask);
        self::assertSame(TaskInterface::SUCCEED, $chainedFooTask->getExecutionState());
        self::assertNotNull($chainedFooTask->getExecutionStartTime());
        self::assertNotNull($chainedFooTask->getExecutionEndTime());

        $chainedBarTask = $chainedTask->getTask('chained_bar');
        self::assertInstanceOf(ShellTask::class, $chainedBarTask);
        self::assertSame(TaskInterface::SUCCEED, $chainedBarTask->getExecutionState());
        self::assertNotNull($chainedBarTask->getExecutionStartTime());
        self::assertNotNull($chainedBarTask->getExecutionEndTime());

        self::assertSame(TaskInterface::SUCCEED, $shellTask->getExecutionState());
        self::assertNotNull($shellTask->getExecutionStartTime());
        self::assertNotNull($shellTask->getExecutionEndTime());
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function testWorkerCanExecuteLongRunningTask(): void
    {
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $application = new Application();
        $application->addCommands([
            new LongExecutionCommand(),
        ]);

        $task = new CommandTask('foo', 'app:long');
        $task->setScheduledAt(new DateTimeImmutable('- 1 month'));
        $task->setSingleRun(true);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new CommandTaskRunner($application),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), new EventDispatcher(), $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create());

        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertNull($task->getAccessLockBag());
        self::assertFalse($worker->getConfiguration()->isRunning());
        self::assertSame(1, $worker->getConfiguration()->getExecutedTasksCount());
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function testWorkerCanExecuteTaskWithExecutionDelay(): void
    {
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExecutionDelay')->willReturn(5000);
        $task->expects(self::once())->method('getAccessLockBag')->willReturn(new AccessLockBag(new Key('foo')));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with(self::equalTo($task))->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, Output::SUCCESS));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            $runner,
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
            new TaskExecutionMiddleware(),
        ]), new EventDispatcher(), $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create());

        self::assertCount(0, $worker->getFailedTasks());
        self::assertSame($task, $worker->getLastExecutedTask());
        self::assertFalse($worker->getConfiguration()->isRunning());
        self::assertSame(1, $worker->getConfiguration()->getExecutedTasksCount());
    }

    /**
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function testWorkerCanStopWithEmptyTaskList(): void
    {
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList());

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), new EventDispatcher(), $lockFactory, $logger);
        self::assertSame(0, $worker->getConfiguration()->getExecutedTasksCount());

        $worker->execute(WorkerConfiguration::create());

        self::assertCount(0, $worker->getFailedTasks());
        self::assertNull($worker->getLastExecutedTask());
        self::assertTrue($worker->getConfiguration()->shouldStop());
        self::assertFalse($worker->getConfiguration()->isRunning());
        self::assertSame(0, $worker->getConfiguration()->getExecutedTasksCount());
    }

    /**
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function testWorkerCanStopWithoutExecutedTasks(): void
    {
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new ShellTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), new EventDispatcher(), $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create());

        self::assertCount(1, $worker->getFailedTasks());
        self::assertNull($worker->getLastExecutedTask());
        self::assertFalse($worker->getConfiguration()->isRunning());
        self::assertSame(0, $worker->getConfiguration()->getExecutedTasksCount());
    }

    /**
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function testWorkerCanStopWhenTasksAreExecutedAndWithoutSleepOptionTwice(): void
    {
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), new EventDispatcher(), $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create());

        self::assertCount(0, $worker->getFailedTasks());
        self::assertFalse($worker->getConfiguration()->isRunning());
        self::assertInstanceOf(NullTask::class, $worker->getLastExecutedTask());
        self::assertSame(1, $worker->getConfiguration()->getExecutedTasksCount());

        $worker->execute(WorkerConfiguration::create());

        self::assertCount(0, $worker->getFailedTasks());
        self::assertFalse($worker->getConfiguration()->isRunning());
        self::assertInstanceOf(NullTask::class, $worker->getLastExecutedTask());
        self::assertSame(1, $worker->getConfiguration()->getExecutedTasksCount());
        self::assertSame(1, $worker->getConfiguration()->getExecutedTasksCount());
    }

    public function testWorkerCanBePaused(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event): void {
            $worker = $event->getWorker();
            $configuration = $worker->getConfiguration();

            if (!$configuration->isRunning()) {
                return;
            }

            $worker->pause();
        });

        $eventDispatcher->addListener(WorkerPausedEvent::class, function (WorkerPausedEvent $event): void {
            $worker = $event->getWorker();

            self::assertFalse($worker->isRunning());
        });

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        self::assertFalse($worker->isRunning());

        $worker->execute(WorkerConfiguration::create());
        self::assertCount(0, $worker->getFailedTasks());
        self::assertSame(1, $worker->getConfiguration()->getExecutedTasksCount());
        self::assertFalse($worker->isRunning());
    }

    public function testWorkerCanBeRestarted(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event): void {
            $worker = $event->getWorker();
            $configuration = $worker->getConfiguration();

            if (!$configuration->isRunning()) {
                return;
            }

            $worker->restart();
        });

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        self::assertFalse($worker->getConfiguration()->shouldStop());
        self::assertFalse($worker->isRunning());

        $worker->execute(WorkerConfiguration::create());

        self::assertFalse($worker->isRunning());
        self::assertCount(0, $worker->getFailedTasks());
        self::assertTrue($worker->getConfiguration()->shouldStop());
    }

    /**
     * @throws Throwable {@see WorkerInterface::preempt()}
     */
    public function testWorkerCanPreempt(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), new EventDispatcher(), $lockFactory, $logger);

        $barTask = new NullTask('bar');
        $randomTask = new NullTask('random');

        $preemptList = new TaskList([
            new NullTask('foo'),
            $barTask,
            $randomTask,
        ]);

        $toPreemptList = new TaskList([
            new NullTask('foo'),
            $barTask,
            $randomTask,
        ]);

        $worker->preempt($preemptList, $toPreemptList);

        self::assertInstanceOf(DateTimeImmutable::class, $barTask->getLastExecution());
        self::assertInstanceOf(DateTimeImmutable::class, $randomTask->getLastExecution());
    }

    /**
     * @throws Throwable {@see WorkerInterface::preempt()}
     */
    public function testWorkerCannotPreemptEmptyList(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), $tracker, new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), new EventDispatcher(), $lockFactory, $logger);

        $barTask = new NullTask('bar');
        $randomTask = new NullTask('random');

        $preemptList = new TaskList([
            new NullTask('foo'),
        ]);

        $toPreemptList = new TaskList([
            new NullTask('foo'),
            $barTask,
            $randomTask,
        ]);

        $worker->preempt($preemptList, $toPreemptList);

        self::assertNull($barTask->getLastExecution());
        self::assertNull($randomTask->getLastExecution());
    }
}
