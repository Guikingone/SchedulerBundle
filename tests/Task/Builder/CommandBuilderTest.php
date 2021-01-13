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
use SchedulerBundle\Task\Builder\CommandBuilder;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $builder = new CommandBuilder();

        static::assertFalse($builder->support('test'));
        static::assertTrue($builder->support('command'));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $builder = new CommandBuilder();

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), $options);

        static::assertInstanceOf(CommandTask::class, $task);
        static::assertSame($options['name'], $task->getName());
        static::assertSame($options['expression'], $task->getExpression());
        static::assertSame($options['command'], $task->getCommand());
        static::assertSame($options['arguments'], $task->getArguments());
        static::assertSame($options['options'], $task->getOptions());
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
                'type' => 'command',
                'command' => 'cache:clear',
                'options' => [
                    '--env' => 'test',
                ],
                'arguments' => [],
                'expression' => '*/5 * * * *',
                'description' => 'A simple cache clear command',
            ],
        ];
        yield [
            [
                'name' => 'bar',
                'type' => 'command',
                'command' => 'cache:clear',
                'options' => [
                    '--env' => 'test',
                ],
                'arguments' => [
                    'test',
                ],
                'expression' => '*/5 * * * *',
                'description' => 'A simple cache clear command',
            ],
        ];
    }
}
