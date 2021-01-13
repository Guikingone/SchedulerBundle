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

        static::assertSame('https://symfony.com', $task->getUrl());
        static::assertSame('GET', $task->getMethod());
        static::assertEmpty($task->getClientOptions());
    }

    public function testTaskCanBeCreatedAndUrlChanged(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET');
        static::assertSame('https://symfony.com', $task->getUrl());

        $task->setUrl('https://symfony.com/test');
        static::assertSame('https://symfony.com/test', $task->getUrl());
    }

    public function testTaskCanBeCreatedAndMethodChanged(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET');
        static::assertSame('https://symfony.com', $task->getUrl());

        $task->setMethod('POST');
        static::assertSame('POST', $task->getMethod());
    }

    public function testTaskCannotBeCreatedWithInvalidOptions(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The following option: "test" is not supported');
        new HttpTask('foo', 'https://symfony.com', 'GET', [
            'test' => 'foo',
        ]);
    }

    public function testTaskCanBeCreatedAndClientOptionsChanged(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET', [
            'http_version' => '2.0',
        ]);
        static::assertArrayHasKey('http_version', $task->getClientOptions());
        static::assertSame('2.0', $task->getClientOptions()['http_version']);

        $task->setClientOptions([
            'http_version' => '1.0',
        ]);
        static::assertArrayHasKey('http_version', $task->getClientOptions());
        static::assertSame('1.0', $task->getClientOptions()['http_version']);
    }
}
