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
        $httpTask = new HttpTask('foo', 'https://symfony.com', 'GET');

        self::assertSame('https://symfony.com', $httpTask->getUrl());
        self::assertSame('GET', $httpTask->getMethod());
        self::assertEmpty($httpTask->getClientOptions());
    }

    public function testTaskCanBeCreatedAndUrlChanged(): void
    {
        $httpTask = new HttpTask('foo', 'https://symfony.com', 'GET');
        self::assertSame('https://symfony.com', $httpTask->getUrl());

        $httpTask->setUrl('https://symfony.com/test');
        self::assertSame('https://symfony.com/test', $httpTask->getUrl());
    }

    public function testTaskCanBeCreatedAndMethodChanged(): void
    {
        $httpTask = new HttpTask('foo', 'https://symfony.com', 'GET');
        self::assertSame('https://symfony.com', $httpTask->getUrl());

        $httpTask->setMethod('POST');
        self::assertSame('POST', $httpTask->getMethod());
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
        $httpTask = new HttpTask('foo', 'https://symfony.com', 'GET', [
            'http_version' => '2.0',
        ]);
        self::assertArrayHasKey('http_version', $httpTask->getClientOptions());
        self::assertSame('2.0', $httpTask->getClientOptions()['http_version']);

        $httpTask->setClientOptions([
            'http_version' => '1.0',
        ]);
        self::assertArrayHasKey('http_version', $httpTask->getClientOptions());
        self::assertSame('1.0', $httpTask->getClientOptions()['http_version']);
    }
}
