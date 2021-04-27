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
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
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
            new FirstInFirstOutPolicy(),
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
            new FirstInFirstOutPolicy(),
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
    }

    public function provideTaskData(): Generator
    {
        yield [
            [
                'name' => 'bar',
                'execution_mode' => 'first_in_first_out',
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
                ],
            ],
        ];
        yield [
            [
                'name' => 'bar',
                'execution_mode' => 'first_in_first_out',
                'tasks' => [
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
            ],
        ];
    }
}
