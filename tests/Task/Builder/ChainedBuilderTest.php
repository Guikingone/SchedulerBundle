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
        ]));

        self::assertFalse($chainedBuilder->support('test'));
        self::assertTrue($chainedBuilder->support('chained'));
    }

    /**
     * @dataProvider provideTaskData
     *
     * @param array<string, mixed> $configuration
     */
    public function testBuilderCanBuildWithoutBuilders(array $configuration): void
    {
        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given task cannot be created as no related builder can be found');
        self::expectExceptionCode(0);
        $chainedBuilder->build(PropertyAccess::createPropertyAccessor(), $configuration);
    }

    /**
     * @dataProvider provideTaskData
     *
     * @param array<string, mixed> $configuration
     */
    public function testBuilderCanBuild(array $configuration): void
    {
        $chainedBuilder = new ChainedBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
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
        self::assertInstanceOf(ShellTask::class, $task->getTask($configuration['tasks'][0]['name']));
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideTaskData(): Generator
    {
        yield [
            [
                'name' => 'bar',
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
