<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ShellTask;
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!']);

        self::assertSame('foo', $shellTask->getName());
        self::assertSame('* * * * *', $shellTask->getExpression());
        self::assertNotEmpty($shellTask->getCommand());
        self::assertContainsEquals('echo', $shellTask->getCommand());
        self::assertContainsEquals('Symfony!', $shellTask->getCommand());
        self::assertSame(60.0, $shellTask->getTimeout());
        self::assertSame(0, $shellTask->getPriority());
    }

    public function testTaskCanBeCreatedWithSpecificCwd(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!'], '/srv/app');

        self::assertSame('/srv/app', $shellTask->getCwd());
    }

    public function testTaskCanBeCreatedWithSpecificCwdAndChangedLater(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!'], '/srv/app');
        self::assertSame('/srv/app', $shellTask->getCwd());

        $shellTask->setCwd(sys_get_temp_dir());
        self::assertSame(sys_get_temp_dir(), $shellTask->getCwd());
    }

    public function testTaskCanBeCreatedWithSpecificEnvironmentVariables(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!'], null, [
            'APP_ENV' => 'test',
        ]);

        self::assertArrayHasKey('APP_ENV', $shellTask->getEnvironmentVariables());
        self::assertSame('test', $shellTask->getEnvironmentVariables()['APP_ENV']);
    }

    public function testTaskCanBeCreatedWithSpecificEnvironmentVariablesAndChangedLater(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!'], null, [
            'APP_ENV' => 'test',
        ]);
        self::assertArrayHasKey('APP_ENV', $shellTask->getEnvironmentVariables());
        self::assertSame('test', $shellTask->getEnvironmentVariables()['APP_ENV']);

        $shellTask->setEnvironmentVariables([
            'APP_ENV' => 'prod',
        ]);
        self::assertArrayHasKey('APP_ENV', $shellTask->getEnvironmentVariables());
        self::assertSame('prod', $shellTask->getEnvironmentVariables()['APP_ENV']);
    }

    public function testTaskCanDefineBeforeSchedulingCallable(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!']);
        $shellTask->beforeScheduling(fn (): bool => false);

        self::assertNotNull($shellTask->getBeforeScheduling());
    }

    public function testTaskCanDefineAfterSchedulingCallable(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!']);
        $shellTask->afterScheduling(fn (): bool => false);

        self::assertNotNull($shellTask->getAfterScheduling());
    }
}
