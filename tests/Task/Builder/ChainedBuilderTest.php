<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Builder;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\FluentExpressionBuilder;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\SchedulePolicy\DeadlinePolicy;
use SchedulerBundle\SchedulePolicy\ExecutionDurationPolicy;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\FirstInLastOutPolicy;
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\SchedulePolicy\NicePolicy;
use SchedulerBundle\SchedulePolicy\PriorityPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\Builder\ChainedBuilder;
use SchedulerBundle\Task\Builder\ShellBuilder;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\ShellTask;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new PriorityPolicy(),
        ]));

        self::assertFalse($chainedBuilder->support('test'));
        self::assertTrue($chainedBuilder->support('chained'));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanBuildWithoutBuilders(array $configuration): void
    {
        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new PriorityPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given task cannot be created as no related builder can be found');
        self::expectExceptionCode(0);
        $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanBuild(array $configuration): void
    {
        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new PriorityPolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanBuildAndSortTasksUsingBatch(array $configuration): void
    {
        $configuration['execution_mode'] = 'batch';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new BatchPolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanSortTasksUsingDeadline(array $configuration): void
    {
        $configuration['execution_mode'] = 'deadline';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new DeadlinePolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanSortTasksUsingExecutionDuration(array $configuration): void
    {
        $configuration['execution_mode'] = 'execution_duration';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new ExecutionDurationPolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanSortTasksUsingFirstInFirstOut(array $configuration): void
    {
        $configuration['execution_mode'] = 'first_in_first_out';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanSortTasksUsingFirstInLastOut(array $configuration): void
    {
        $configuration['execution_mode'] = 'first_in_last_out';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new FirstInLastOutPolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanSortTasksUsingIdle(array $configuration): void
    {
        $configuration['execution_mode'] = 'idle';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new IdlePolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanSortTasksUsingMemoryUsage(array $configuration): void
    {
        $configuration['execution_mode'] = 'memory_usage';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new MemoryUsagePolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanSortTasksUsingNice(array $configuration): void
    {
        $configuration['execution_mode'] = 'nice';

        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]), new SchedulePolicyOrchestrator([
            new NicePolicy(),
        ]), [
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ]);

        $task = $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
    }

    public function provideTaskData(): Generator
    {
        yield [
            [
                'name' => 'bar',
                'execution_mode' => 'priority',
                'tasks' => [
                    [
                        'name' => 'foo',
                        'type' => 'shell',
                        'command' => ['ls',  '-al'],
                        'environment_variables' => [
                            'APP_ENV' => 'test',
                        ],
                        'timeout' => 50,
                        'expression' => '* * * * *',
                        'description' => 'A simple ls command',
                    ],
                    [
                        'name' => 'bar',
                        'type' => 'shell',
                        'command' => ['ls',  '-l'],
                        'environment_variables' => [
                            'APP_ENV' => 'test',
                        ],
                        'timeout' => 50,
                        'expression' => '* * * * *',
                        'description' => 'A second ls command',
                    ],
                ],
            ],[
                'name' => 'bar',
                'execution_mode' => 'priority',
                'tasks' => [
                    [
                        'name' => 'foo',
                        'type' => 'shell',
                        'command' => ['ls',  '-al'],
                    ],
                    [
                        'name' => 'bar',
                        'type' => 'shell',
                        'command' => ['ls',  '-l'],
                    ],
                ],
            ],
        ];
    }
}
