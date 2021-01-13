<?php

declare(strict_types=1);

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

        self::assertFalse($builder->support('test'));
        self::assertTrue($builder->support());
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $builder = new NullBuilder();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $task = $builder->build($propertyAccessor, $options);

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame($options['name'], $task->getName());
        self::assertSame($options['expression'], $task->getExpression());
        self::assertNull($task->getDescription());
        self::assertSame($options['queued'], $task->isQueued());
        self::assertSame($options['timezone'], $task->getTimezone()->getName());
        self::assertSame(TaskInterface::ENABLED, $task->getState());
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
