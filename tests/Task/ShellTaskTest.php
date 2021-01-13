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
use SchedulerBundle\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!']);

        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
        static::assertNotEmpty($task->getCommand());
        static::assertContainsEquals('echo', $task->getCommand());
        static::assertContainsEquals('Symfony!', $task->getCommand());
        static::assertSame(60.0, $task->getTimeout());
    }

    public function testTaskCanBeCreatedWithSpecificCwd(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], '/srv/app');

        static::assertSame('/srv/app', $task->getCwd());
    }

    public function testTaskCanBeCreatedWithSpecificCwdAndChangedLater(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], '/srv/app');
        static::assertSame('/srv/app', $task->getCwd());

        $task->setCwd(sys_get_temp_dir());
        static::assertSame(sys_get_temp_dir(), $task->getCwd());
    }

    public function testTaskCanBeCreatedWithSpecificEnvironmentVariables(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], null, [
            'APP_ENV' => 'test',
        ]);

        static::assertArrayHasKey('APP_ENV', $task->getEnvironmentVariables());
        static::assertSame('test', $task->getEnvironmentVariables()['APP_ENV']);
    }

    public function testTaskCanBeCreatedWithSpecificEnvironmentVariablesAndChangedLater(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony!'], null, [
            'APP_ENV' => 'test',
        ]);
        static::assertArrayHasKey('APP_ENV', $task->getEnvironmentVariables());
        static::assertSame('test', $task->getEnvironmentVariables()['APP_ENV']);

        $task->setEnvironmentVariables([
            'APP_ENV' => 'prod',
        ]);
        static::assertArrayHasKey('APP_ENV', $task->getEnvironmentVariables());
        static::assertSame('prod', $task->getEnvironmentVariables()['APP_ENV']);
    }
}
