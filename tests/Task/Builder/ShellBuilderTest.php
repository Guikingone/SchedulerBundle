<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Builder;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\FluentExpressionBuilder;
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
        $shellBuilder = new ShellBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        self::assertFalse($shellBuilder->support('test'));
        self::assertTrue($shellBuilder->support('shell'));
    }

    /**
     * @dataProvider provideTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $shellBuilder = new ShellBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $shellBuilder->build(PropertyAccess::createPropertyAccessor(), $options);

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertSame($options['name'], $task->getName());
        self::assertSame($options['expression'], $task->getExpression());
        self::assertSame($options['command'], $task->getCommand());
        self::assertSame($options['cwd'] ?? null, $task->getCwd());
        self::assertNotEmpty($task->getEnvironmentVariables());
        self::assertEquals($options['environment_variables'], $task->getEnvironmentVariables());
        self::assertSame((float) $options['timeout'], $task->getTimeout());
        self::assertSame($options['description'], $task->getDescription());
        self::assertFalse($task->isQueued());
        self::assertNull($task->getTimezone());
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        $task = $shellBuilder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'foo',
            'command' => ['ls', '-al'],
        ]);

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertSame(60.0, $task->getTimeout());
        self::assertEmpty($task->getEnvironmentVariables());
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideTaskData(): Generator
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
                'cwd' => 'foo',
            ],
        ];
    }
}
