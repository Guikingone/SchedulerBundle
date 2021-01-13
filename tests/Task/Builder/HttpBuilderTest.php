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
use SchedulerBundle\Task\Builder\HttpBuilder;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $builder = new HttpBuilder();

        static::assertFalse($builder->support('test'));
        static::assertTrue($builder->support('http'));
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testTaskCanBeBuilt(array $options): void
    {
        $builder = new HttpBuilder();

        $task = $builder->build(PropertyAccess::createPropertyAccessor(), $options);

        static::assertInstanceOf(HttpTask::class, $task);
        static::assertSame($options['name'], $task->getName());
        static::assertSame($options['expression'], $task->getExpression());
        static::assertSame($options['url'], $task->getUrl());
        static::assertSame($options['method'], $task->getMethod());
        static::assertSame($options['client_options'], $task->getClientOptions());
        static::assertNull($task->getDescription());
        static::assertFalse($task->isQueued());
        static::assertNull($task->getTimezone());
        static::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function provideTaskData(): \Generator
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
