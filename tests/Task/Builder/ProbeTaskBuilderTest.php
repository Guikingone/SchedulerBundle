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
                'errorOnFailedTask' => true,
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
