<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\CallbackTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTaskTest extends TestCase
{
    public function testTaskCannotBeCreatedWithInvalidCallback(): void
    {
        self::expectException(InvalidArgumentException::class);
        new CallbackTask('foo', [$this, 'test']);
    }

    public function testTaskCanBeCreatedWithValidCallable(): void
    {
        $task = new CallbackTask('foo', [new FooService(), 'echo']);

        self::assertNotEmpty($task->getCallback());
        self::assertEmpty($task->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallback(): void
    {
        $task = new CallbackTask('foo', function (): void {
            echo 'test';
        });

        self::assertEmpty($task->getArguments());
    }

    public function testTaskCanBeCreatedWithCallbackAndChangeCallbackLater(): void
    {
        $task = new CallbackTask('foo', function (): void {
            echo 'test';
        });

        self::assertEmpty($task->getArguments());

        $task->setCallback(function (): void {
            echo 'Symfony';
        });
    }

    public function testTaskCanBeCreatedWithValidCallbackAndArguments(): void
    {
        $task = new CallbackTask('foo', function ($value): void {
            echo $value;
        }, ['value' => 'test']);

        self::assertNotEmpty($task->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallbackAndSetArgumentsLater(): void
    {
        $task = new CallbackTask('foo', function ($value): void {
            echo $value;
        });
        $task->setArguments(['value' => 'test']);

        self::assertNotEmpty($task->getArguments());
    }
}

final class FooService
{
    public function echo(): void
    {
        echo 'Symfony';
    }
}
