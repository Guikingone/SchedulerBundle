<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Builder;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
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
        $builder = new ChainedBuilder();

        self::assertFalse($builder->support('test'));
        self::assertTrue($builder->support('chained'));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanBuildWithoutBuilders(array $configuration): void
    {
        $builder = new ChainedBuilder();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given task cannot be created as no related builder can be found');
        self::expectExceptionCode(0);
        $builder->build(PropertyAccess::createPropertyAccessor(), $configuration);
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testBuilderCanBuild(array $configuration): void
    {
        $builder = new ChainedBuilder([
            new ShellBuilder(),
        ]);

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), $configuration);

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
    }

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
