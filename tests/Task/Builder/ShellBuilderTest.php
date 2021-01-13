<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Task\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use SchedulerBundle\Task\Builder\ShellBuilder;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $builder = new ShellBuilder();

        static::assertFalse($builder->support('test'));
        static::assertTrue($builder->support('shell'));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $builder = new ShellBuilder();

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), $options);

        static::assertInstanceOf(ShellTask::class, $task);
        static::assertSame($options['name'], $task->getName());
        static::assertSame($options['expression'], $task->getExpression());
        static::assertSame($options['command'], $task->getCommand());
        static::assertNull($task->getCwd());
        static::assertNotEmpty($task->getEnvironmentVariables());
        static::assertSame((float) $options['timeout'], $task->getTimeout());
        static::assertSame($options['description'], $task->getDescription());
        static::assertFalse($task->isQueued());
        static::assertNull($task->getTimezone());
        static::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function provideTaskData(): \Generator
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
}
