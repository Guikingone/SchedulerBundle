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
use SchedulerBundle\Task\Builder\NullBuilder;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $builder = new NullBuilder();

        static::assertFalse($builder->support('test'));
        static::assertTrue($builder->support());
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $builder = new NullBuilder();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $task = $builder->build($propertyAccessor, $options);

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame($options['name'], $task->getName());
        static::assertSame($options['expression'], $task->getExpression());
        static::assertNull($task->getDescription());
        static::assertSame($options['queued'], $task->isQueued());
        static::assertSame($options['timezone'], $task->getTimezone()->getName());
        static::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function provideTaskData(): \Generator
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
}
