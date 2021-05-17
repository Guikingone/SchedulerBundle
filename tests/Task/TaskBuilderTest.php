<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\FluentExpressionBuilder;
use SchedulerBundle\Task\Builder\BuilderInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\Builder\CommandBuilder;
use SchedulerBundle\Task\Builder\HttpBuilder;
use SchedulerBundle\Task\Builder\NullBuilder;
use SchedulerBundle\Task\Builder\ShellBuilder;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskBuilderTest extends TestCase
{
    public function testBuilderCannotBuildWithoutBuilders(): void
    {
        $taskBuilder = new TaskBuilder([
            new NullBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ], PropertyAccess::createPropertyAccessor());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task cannot be created as no builder has been defined for "test"');
        self::expectExceptionCode(0);
        $taskBuilder->create([
            'type' => 'test',
        ]);
    }

    /**
     * @dataProvider provideNullTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testBuilderCanCreateNullTask(array $options): void
    {
        $invalidBuilder = $this->createMock(BuilderInterface::class);
        $invalidBuilder->expects(self::once())->method('support')->with(self::equalTo('null'))->willReturn(false);

        $taskBuilder = new TaskBuilder([
            $invalidBuilder,
            new NullBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(NullTask::class, $taskBuilder->create($options));
    }

    /**
     * @dataProvider provideShellTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testBuilderCanCreateShellTask(array $options): void
    {
        $invalidBuilder = $this->createMock(BuilderInterface::class);
        $invalidBuilder->expects(self::once())->method('support')->with(self::equalTo('shell'))->willReturn(false);

        $taskBuilder = new TaskBuilder([
            $invalidBuilder,
            new ShellBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(ShellTask::class, $taskBuilder->create($options));
    }

    /**
     * @dataProvider provideCommandTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testBuilderCanCreateCommandTask(array $options): void
    {
        $invalidBuilder = $this->createMock(BuilderInterface::class);
        $invalidBuilder->expects(self::once())->method('support')->with(self::equalTo('command'))->willReturn(false);

        $taskBuilder = new TaskBuilder([
            $invalidBuilder,
            new CommandBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(CommandTask::class, $taskBuilder->create($options));
    }

    /**
     * @dataProvider provideHttpTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testBuilderCanCreateHttpTask(array $options): void
    {
        $invalidBuilder = $this->createMock(BuilderInterface::class);
        $invalidBuilder->expects(self::once())->method('support')->with(self::equalTo('http'))->willReturn(false);

        $taskBuilder = new TaskBuilder([
            $invalidBuilder,
            new HttpBuilder(new ExpressionBuilder([
                new CronExpressionBuilder(),
                new ComputedExpressionBuilder(),
                new FluentExpressionBuilder(),
            ])),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(HttpTask::class, $taskBuilder->create($options));
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideNullTaskData(): Generator
    {
        yield [
            [
                'name' => 'foo',
                'type' => 'null',
                'expression' => '* * * * *',
                'queued' => false,
                'timezone' => 'UTC',
                'environment_variables' => [],
                'client_options' => [],
                'arguments' => [],
                'options' => [],
            ],
        ];
        yield [
            [
                'name' => 'bar',
                'type' => 'null',
                'expression' => '* * * * *',
                'queued' => false,
                'timezone' => 'UTC',
                'environment_variables' => [],
                'client_options' => [],
                'arguments' => [],
                'options' => [],
            ],
        ];
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideShellTaskData(): Generator
    {
        yield [
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
        ];
        yield [
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
        ];
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideCommandTaskData(): Generator
    {
        yield [
            [
                'name' => 'foo',
                'type' => 'command',
                'command' => 'cache:clear',
                'options' => [
                    '--env' => 'test',
                ],
                'expression' => '*/5 * * * *',
                'description' => 'A simple cache clear command',
            ],
        ];
        yield [
            [
                'name' => 'bar',
                'type' => 'command',
                'command' => 'cache:clear',
                'arguments' => [
                    'test',
                ],
                'expression' => '*/5 * * * *',
                'description' => 'A simple cache clear command',
            ],
        ];
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideHttpTaskData(): Generator
    {
        yield [
            [
                'name' => 'foo',
                'type' => 'http',
                'url' => 'https://symfony.com',
                'method' => 'GET',
                'client_options' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
                'expression' => '*/5 * * * *',
            ],
        ];
        yield [
            [
                'name' => 'bar',
                'type' => 'http',
                'url' => 'https://google.com',
                'method' => 'GET',
                'client_options' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
                'expression' => '*/5 * * * *',
            ],
        ];
    }
}
