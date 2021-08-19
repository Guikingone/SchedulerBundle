<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RunnerRegistryTest extends TestCase
{
    public function testRegistryCanFilterRunnerList(): void
    {
        $registry = new RunnerRegistry([
            new CallbackTaskRunner(),
            new ShellTaskRunner(),
            new NullTaskRunner(),
        ]);

        self::assertCount(3, $registry);

        $filteredList = $registry->filter(fn (RunnerInterface $runner): bool => $runner->support(new NullTask('foo')));
        self::assertCount(1, $filteredList);
    }

    public function testRegistryCannotFindRunnerWithoutSupportingRunners(): void
    {
        $registry = new RunnerRegistry([
            new ShellTaskRunner(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('No runner found for this task');
        self::expectExceptionCode(0);
        $registry->find(new NullTask('foo'));
    }

    public function testRegistryCannotFindRunnerWithMultipleSupportingRunners(): void
    {
        $registry = new RunnerRegistry([
            new NullTaskRunner(),
            new NullTaskRunner(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('More than one runner found, consider improving the task and/or the runner(s)');
        self::expectExceptionCode(0);
        $registry->find(new NullTask('foo'));
    }

    public function testRegistryCanFindRunner(): void
    {
        $registry = new RunnerRegistry([
            new CallbackTaskRunner(),
            new ShellTaskRunner(),
            new NullTaskRunner(),
        ]);

        $runner = $registry->find(new NullTask('foo'));
        self::assertInstanceOf(NullTaskRunner::class, $runner);
    }

    public function testRegistryCannotReturnCurrentRunnerWhenEmpty(): void
    {
        $registry = new RunnerRegistry([]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current runner cannot be found');
        self::expectExceptionCode(0);
        $registry->current();
    }

    public function testRegistryCanReturnCurrentRunner(): void
    {
        $registry = new RunnerRegistry([
            new CallbackTaskRunner(),
            new ShellTaskRunner(),
            new NullTaskRunner(),
        ]);

        $runner = $registry->current();
        self::assertInstanceOf(CallbackTaskRunner::class, $runner);
    }
}
