<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Builder;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\FluentExpressionBuilder;
use SchedulerBundle\Task\NullTask;
use Symfony\Component\PropertyAccess\PropertyAccess;
use SchedulerBundle\Task\Builder\NullBuilder;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $nullBuilder = new NullBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        self::assertFalse($nullBuilder->support('test'));
        self::assertTrue($nullBuilder->support('null'));
    }

    /**
     * @dataProvider provideTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $nullBuilder = new NullBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $task = $nullBuilder->build($propertyAccessor, $options);

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame($options['name'], $task->getName());
        self::assertSame($options['expression'], $task->getExpression());
        self::assertNull($task->getDescription());
        self::assertSame($options['queued'], $task->isQueued());

        $timezone = $task->getTimezone();
        self::assertNotNull($timezone);
        self::assertSame($options['timezone'], $timezone->getName());

        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideTaskData(): Generator
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
