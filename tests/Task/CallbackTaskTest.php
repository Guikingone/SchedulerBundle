<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\CallbackTask;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Tests\SchedulerBundle\Task\Assets\FooService;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTaskTest extends TestCase
{
    public function testTaskCannotBeCreatedWithInvalidCallback(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "callback" with value 135 is expected to be of type "callable" or "string" or "array", but is of type "int"');
        self::expectExceptionCode(0);
        new CallbackTask('foo', 135);
    }

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
