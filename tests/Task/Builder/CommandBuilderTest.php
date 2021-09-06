<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Builder;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\FluentExpressionBuilder;
use SchedulerBundle\Task\CommandTask;
use Symfony\Component\PropertyAccess\PropertyAccess;
use SchedulerBundle\Task\Builder\CommandBuilder;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $commandBuilder = new CommandBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        self::assertFalse($commandBuilder->support('test'));
        self::assertTrue($commandBuilder->support('command'));
    }

    /**
     * @dataProvider provideTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $commandBuilder = new CommandBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $commandBuilder->build(PropertyAccess::createPropertyAccessor(), $options);

        self::assertInstanceOf(CommandTask::class, $task);
        self::assertSame($options['name'], $task->getName());
        self::assertSame($options['expression'], $task->getExpression());
        self::assertSame($options['command'], $task->getCommand());
        self::assertSame($options['arguments'], $task->getArguments());
        self::assertSame($options['options'], $task->getOptions());
        self::assertSame($options['description'], $task->getDescription());
        self::assertFalse($task->isQueued());
        self::assertNull($task->getTimezone());
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCanBeBuiltWithNullArguments(): void
    {
        $commandBuilder = new CommandBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $commandBuilder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'foo',
            'type' => 'command',
            'command' => 'cache:clear',
            'options' => [
                '--env' => 'test',
            ],
            'arguments' => null,
            'expression' => '*/5 * * * *',
            'description' => 'A simple cache clear command',
        ]);

        self::assertInstanceOf(CommandTask::class, $task);
        self::assertCount(0, $task->getArguments());
    }

    public function testTaskCanBeBuiltWithNullOptions(): void
    {
        $commandBuilder = new CommandBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $commandBuilder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'foo',
            'type' => 'command',
            'command' => 'cache:clear',
            'options' => null,
            'arguments' => [],
            'expression' => '*/5 * * * *',
            'description' => 'A simple cache clear command',
        ]);

        self::assertInstanceOf(CommandTask::class, $task);
        self::assertCount(0, $task->getOptions());
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideTaskData(): Generator
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
