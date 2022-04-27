<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\CallbackTask;
use Tests\SchedulerBundle\Task\Assets\FooService;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTaskTest extends TestCase
{
    public function testTaskCanBeCreatedWithValidCallable(): void
    {
        $callbackTask = new CallbackTask('foo', function (): void {
            (new FooService())->echo();
        });

        self::assertNotEmpty($callbackTask->getCallback());
        self::assertEmpty($callbackTask->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallback(): void
    {
        $callbackTask = new CallbackTask('foo', function (): void {
            echo 'test';
        });

        self::assertEmpty($callbackTask->getArguments());
    }

    public function testTaskCanBeCreatedWithCallbackAndChangeCallbackLater(): void
    {
        $callbackTask = new CallbackTask('foo', function (): void {
            echo 'test';
        });

        self::assertEmpty($callbackTask->getArguments());

        $callbackTask->setCallback(function (): void {
            echo 'Symfony';
        });
    }

    public function testTaskCanBeCreatedWithValidCallbackAndArguments(): void
    {
        $callbackTask = new CallbackTask('foo', function (string $value): void {
            echo $value;
        }, ['value' => 'test']);

        self::assertNotEmpty($callbackTask->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallbackAndSetArgumentsLater(): void
    {
        $callbackTask = new CallbackTask('foo', function (string $value): void {
            echo $value;
        });
        $callbackTask->setArguments(['value' => 'test']);

        self::assertNotEmpty($callbackTask->getArguments());
    }
}
