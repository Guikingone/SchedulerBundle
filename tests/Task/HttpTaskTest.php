<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\HttpTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET');

        self::assertSame('https://symfony.com', $task->getUrl());
        self::assertSame('GET', $task->getMethod());
        self::assertEmpty($task->getClientOptions());
    }

    public function testTaskCanBeCreatedAndUrlChanged(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET');
        self::assertSame('https://symfony.com', $task->getUrl());

        $task->setUrl('https://symfony.com/test');
        self::assertSame('https://symfony.com/test', $task->getUrl());
    }

    public function testTaskCanBeCreatedAndMethodChanged(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET');
        self::assertSame('https://symfony.com', $task->getUrl());

        $task->setMethod('POST');
        self::assertSame('POST', $task->getMethod());
    }

    public function testTaskCannotBeCreatedWithInvalidOptions(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The following option: "test" is not supported');
        new HttpTask('foo', 'https://symfony.com', 'GET', [
            'test' => 'foo',
        ]);
    }

    public function testTaskCanBeCreatedAndClientOptionsChanged(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET', [
            'http_version' => '2.0',
        ]);
        self::assertArrayHasKey('http_version', $task->getClientOptions());
        self::assertSame('2.0', $task->getClientOptions()['http_version']);

        $task->setClientOptions([
            'http_version' => '1.0',
        ]);
        self::assertArrayHasKey('http_version', $task->getClientOptions());
        self::assertSame('1.0', $task->getClientOptions()['http_version']);
    }
}
