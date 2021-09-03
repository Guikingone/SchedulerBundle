<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Builder;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\FluentExpressionBuilder;
use SchedulerBundle\Task\Builder\ProbeTaskBuilder;
use SchedulerBundle\Task\ProbeTask;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $builder = new ProbeTaskBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        self::assertFalse($builder->support('test'));
        self::assertTrue($builder->support('probe'));
    }

    /**
     * @param array<string, string|bool|int> $options
     *
     * @dataProvider provideTaskData
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $builder = new ProbeTaskBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), $options);

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame($options['name'], $task->getName());
        self::assertSame($options['externalProbePath'], $task->getExternalProbePath());
        self::assertGreaterThanOrEqual(0, $task->getDelay());
    }

    public function testTaskCanBeBuiltWithoutExtraInformations(): void
    {
        $builder = new ProbeTaskBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'bar',
            'type' => 'probe',
            'externalProbePath' => '/_probe',
            'errorOnFailedTasks' => true,
            'delay' => 100,
        ]);

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame('bar', $task->getName());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertTrue($task->getErrorOnFailedTasks());
        self::assertSame(100, $task->getDelay());

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'random',
            'type' => 'probe',
            'externalProbePath' => '/_probe',
            'delay' => 100,
        ]);

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame('random', $task->getName());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertFalse($task->getErrorOnFailedTasks());
        self::assertSame(100, $task->getDelay());

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'foo',
            'type' => 'probe',
            'externalProbePath' => '/_probe',
        ]);

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertFalse($task->getErrorOnFailedTasks());
        self::assertSame(0, $task->getDelay());
    }

    public function testTaskCanBeBuiltWithNullErrorOnFailedTasks(): void
    {
        $builder = new ProbeTaskBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'bar',
            'type' => 'probe',
            'externalProbePath' => '/_probe',
            'errorOnFailedTasks' => null,
            'delay' => 100,
        ]);

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame('bar', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertFalse($task->getErrorOnFailedTasks());
        self::assertSame(100, $task->getDelay());
    }

    public function testTaskCanBeBuiltWithNullDelay(): void
    {
        $builder = new ProbeTaskBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'bar',
            'type' => 'probe',
            'externalProbePath' => '/_probe',
            'delay' => null,
        ]);

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame('bar', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertFalse($task->getErrorOnFailedTasks());
        self::assertSame(0, $task->getDelay());
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideTaskData(): Generator
    {
        yield 'Full configuration' => [
            [
                'name' => 'foo',
                'type' => 'probe',
                'externalProbePath' => '/_probe',
                'errorOnFailedTasks' => true,
                'delay' => 100,
            ],
        ];
        yield 'Short configuration' => [
            [
                'name' => 'bar',
                'type' => 'probe',
                'externalProbePath' => '/_probe',
            ],
        ];
    }
}
