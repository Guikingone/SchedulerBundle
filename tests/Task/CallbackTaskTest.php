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
        $callbackTask = new CallbackTask(name: 'foo', callback: static function (): void {
            (new FooService())->echo();
        });

        self::assertNotEmpty($callbackTask->getCallback());
        self::assertEmpty($callbackTask->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallback(): void
    {
        $callbackTask = new CallbackTask(name: 'foo', callback: static function (): void {
            echo 'test';
        });

        self::assertEmpty($callbackTask->getArguments());
    }

    public function testTaskCanBeCreatedWithCallbackAndChangeCallbackLater(): void
    {
        $callbackTask = new CallbackTask(name: 'foo', callback: static function (): void {
            echo 'test';
        });

        self::assertEmpty($callbackTask->getArguments());

        $callbackTask->setCallback(callback: static function (): void {
            echo 'Symfony';
        });
    }

    public function testTaskCanBeCreatedWithValidCallbackAndArguments(): void
    {
        $callbackTask = new CallbackTask(name: 'foo', callback: static function (string $value): void {
            echo $value;
        }, arguments: ['value' => 'test']);

        self::assertNotEmpty($callbackTask->getArguments());
    }

    public function testTaskCanBeCreatedWithValidCallbackAndSetArgumentsLater(): void
    {
        $callbackTask = new CallbackTask(name: 'foo', callback: static function (string $value): void {
            echo $value;
        });
        $callbackTask->setArguments(arguments: ['value' => 'test']);

        self::assertNotEmpty($callbackTask->getArguments());
    }
}
