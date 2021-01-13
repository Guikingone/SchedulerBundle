<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\CallbackTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTaskTest extends TestCase
{
    public function testTaskCannotBeCreatedWithInvalidCallback(): void
    {
        static::expectException(\InvalidArgumentException::class);
        new CallbackTask('foo', [$this, 'test']);
    }

    public function testTaskCanBeCreatedWithValidCallable(): void
    {
        $task = new CallbackTask('foo', [new FooService(), 'echo']);

        static::assertNotEmpty($task->getCallback());
        static::assertEmpty($task->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallback(): void
    {
        $task = new CallbackTask('foo', function () {
            echo 'test';
        });

        static::assertEmpty($task->getArguments());
    }

    public function testTaskCanBeCreatedWithCallbackAndChangeCallbackLater(): void
    {
        $task = new CallbackTask('foo', function () {
            echo 'test';
        });

        static::assertEmpty($task->getArguments());

        $task->setCallback(function () {
            echo 'Symfony';
        });
    }

    public function testTaskCanBeCreatedWithValidCallbackAndArguments(): void
    {
        $task = new CallbackTask('foo', function ($value) {
            echo $value;
        }, ['value' => 'test']);

        static::assertNotEmpty($task->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallbackAndSetArgumentsLater(): void
    {
        $task = new CallbackTask('foo', function ($value) {
            echo $value;
        });
        $task->setArguments(['value' => 'test']);

        static::assertNotEmpty($task->getArguments());
    }
}

final class FooService
{
    public function echo(): void
    {
        echo 'Symfony';
    }
}
