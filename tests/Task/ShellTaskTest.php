<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!']);

        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertNotEmpty($task->getCommand());
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony!', $task->getCommand());
        self::assertSame(60.0, $task->getTimeout());
    }

    public function testTaskCanBeCreatedWithSpecificCwd(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], '/srv/app');

        self::assertSame('/srv/app', $task->getCwd());
    }

    public function testTaskCanBeCreatedWithSpecificCwdAndChangedLater(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], '/srv/app');
        self::assertSame('/srv/app', $task->getCwd());

        $task->setCwd(\sys_get_temp_dir());
        self::assertSame(\sys_get_temp_dir(), $task->getCwd());
    }

    public function testTaskCanBeCreatedWithSpecificEnvironmentVariables(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], null, [
            'APP_ENV' => 'test',
        ]);

        self::assertArrayHasKey('APP_ENV', $task->getEnvironmentVariables());
        self::assertSame('test', $task->getEnvironmentVariables()['APP_ENV']);
    }

    public function testTaskCanBeCreatedWithSpecificEnvironmentVariablesAndChangedLater(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], null, [
            'APP_ENV' => 'test',
        ]);
        self::assertArrayHasKey('APP_ENV', $task->getEnvironmentVariables());
        self::assertSame('test', $task->getEnvironmentVariables()['APP_ENV']);

        $task->setEnvironmentVariables([
            'APP_ENV' => 'prod',
        ]);
        self::assertArrayHasKey('APP_ENV', $task->getEnvironmentVariables());
        self::assertSame('prod', $task->getEnvironmentVariables()['APP_ENV']);
    }
}
