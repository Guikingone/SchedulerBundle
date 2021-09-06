<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Builder;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\FluentExpressionBuilder;
use SchedulerBundle\Task\HttpTask;
use Symfony\Component\PropertyAccess\PropertyAccess;
use SchedulerBundle\Task\Builder\HttpBuilder;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $httpBuilder = new HttpBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        self::assertFalse($httpBuilder->support('test'));
        self::assertTrue($httpBuilder->support('http'));
    }

    /**
     * @dataProvider provideTaskData
     *
     * @param array<string, mixed> $options
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $httpBuilder = new HttpBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $httpBuilder->build(PropertyAccess::createPropertyAccessor(), $options);

        self::assertInstanceOf(HttpTask::class, $task);
        self::assertSame($options['name'], $task->getName());
        self::assertSame($options['expression'], $task->getExpression());
        self::assertSame($options['url'], $task->getUrl());
        self::assertSame($options['method'], $task->getMethod());
        self::assertSame($options['client_options'], $task->getClientOptions());
        self::assertNull($task->getDescription());
        self::assertFalse($task->isQueued());
        self::assertNull($task->getTimezone());
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCanBeBuiltWithNullableMethod(): void
    {
        $httpBuilder = new HttpBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $httpBuilder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'bar',
            'type' => 'http',
            'url' => 'https://google.com',
            'method' => null,
            'client_options' => [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ],
            'expression' => '*/5 * * * *',
        ]);

        self::assertInstanceOf(HttpTask::class, $task);
        self::assertSame('GET', $task->getMethod());
        self::assertSame([
            'headers' => [
                'Accept' => 'application/json',
            ],
        ], $task->getClientOptions());
    }

    public function testTaskCanBeBuiltWithNullableClientOptions(): void
    {
        $httpBuilder = new HttpBuilder(new ExpressionBuilder([
            new CronExpressionBuilder(),
            new ComputedExpressionBuilder(),
            new FluentExpressionBuilder(),
        ]));

        $task = $httpBuilder->build(PropertyAccess::createPropertyAccessor(), [
            'name' => 'bar',
            'type' => 'http',
            'url' => 'https://google.com',
            'method' => 'GET',
            'client_options' => null,
            'expression' => '*/5 * * * *',
        ]);

        self::assertInstanceOf(HttpTask::class, $task);
        self::assertSame('GET', $task->getMethod());
        self::assertSame([], $task->getClientOptions());
    }

    /**
     * @return Generator<array<int, array<string, mixed>>>
     */
    public function provideTaskData(): Generator
    {
        yield [
            [
                'name' => 'foo',
                'type' => 'http',
                'url' => 'https://symfony.com',
                'method' => 'GET',
                'client_options' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
                'expression' => '*/5 * * * *',
            ],
        ];
        yield [
            [
                'name' => 'bar',
                'type' => 'http',
                'url' => 'https://google.com',
                'method' => 'GET',
                'client_options' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
                'expression' => '*/5 * * * *',
            ],
        ];
    }
}
