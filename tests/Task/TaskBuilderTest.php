<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
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
        $builder = new TaskBuilder([
            new NullBuilder(),
        ], PropertyAccess::createPropertyAccessor());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task cannot be created as no builder has been defined for "test"');
        $builder->create([
            'type' => 'test',
        ]);
    }

    /**
     * @dataProvider provideNullTaskData
     */
    public function testBuilderCanCreateNullTask(array $options): void
    {
        $builder = new TaskBuilder([
            new NullBuilder(),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(NullTask::class, $builder->create($options));
    }

    /**
     * @dataProvider provideShellTaskData
     */
    public function testBuilderCanCreateShellTask(array $options): void
    {
        $builder = new TaskBuilder([
            new ShellBuilder(),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(ShellTask::class, $builder->create($options));
    }

    /**
     * @dataProvider provideCommandTaskData
     */
    public function testBuilderCanCreateCommandTask(array $options): void
    {
        $builder = new TaskBuilder([
            new CommandBuilder(),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(CommandTask::class, $builder->create($options));
    }

    /**
     * @dataProvider provideHttpTaskData
     */
    public function testBuilderCanCreateHttpTask(array $options): void
    {
        $builder = new TaskBuilder([
            new HttpBuilder(),
        ], PropertyAccess::createPropertyAccessor());

        self::assertInstanceOf(HttpTask::class, $builder->create($options));
    }

    public function provideNullTaskData(): \Generator
    {
        yield [
            [
                'name' => 'foo',
                'type' => null,
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
                'type' => null,
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

    public function provideShellTaskData(): \Generator
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

    public function provideCommandTaskData(): \Generator
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

    public function provideHttpTaskData(): \Generator
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
