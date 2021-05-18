<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\DebugProbeCommand;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugProbeCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $command = new DebugProbeCommand($probe, $scheduler);

        self::assertSame('scheduler:debug:probe', $command->getName());
        self::assertSame(0, $command->getDefinition()->getArgumentCount());
        self::assertTrue($command->getDefinition()->hasOption('external'));
        self::assertNull($command->getDefinition()->getOption('external')->getShortcut());
        self::assertFalse($command->getDefinition()->getOption('external')->isValueRequired());
        self::assertSame('Define if the external probes state must be displayed', $command->getDefinition()->getOption('external')->getDescription());
    }

    public function testCommandCanReturnCurrentProbeState(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getExecutedTasks')->willReturn(1);
        $probe->expects(self::once())->method('getFailedTasks')->willReturn(5);
        $probe->expects(self::once())->method('getScheduledTasks')->willReturn(10);

        $command = new DebugProbeCommand($probe, $scheduler);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('[INFO] The displayed probe state is the one found at', $tester->getDisplay());
        self::assertStringContainsString('Executed tasks', $tester->getDisplay());
        self::assertStringContainsString('1', $tester->getDisplay());
        self::assertStringContainsString('Failed tasks', $tester->getDisplay());
        self::assertStringContainsString('5', $tester->getDisplay());
        self::assertStringContainsString('Scheduled tasks', $tester->getDisplay());
        self::assertStringContainsString('10', $tester->getDisplay());
    }

    public function testCommandCannotReturnExternalProbeStateWhenEmpty(): void
    {
        $probe = $this->createMock(ProbeInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList());

        $command = new DebugProbeCommand($probe, $scheduler);

        $tester = new CommandTester($command);
        $tester->execute([
            '--external' => true,
        ]);

        self::assertStringContainsString('[WARNING] No external probe found', $tester->getDisplay());
        self::assertStringNotContainsString('Name', $tester->getDisplay());
        self::assertStringNotContainsString('State', $tester->getDisplay());
        self::assertStringNotContainsString('Last execution', $tester->getDisplay());
        self::assertStringNotContainsString('Execution state', $tester->getDisplay());
    }

    public function testCommandCanReturnSingleExternalProbeState(): void
    {
        $probe = $this->createMock(ProbeInterface::class);

        $executionDate = new DatetimeImmutable();
        $probeTask = new ProbeTask('foo', '/_external_path');
        $probeTask->setLastExecution($executionDate);
        $probeTask->setState(TaskInterface::PAUSED);
        $probeTask->setExecutionState(TaskInterface::SUCCEED);

        $nullTask = new NullTask('bar');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([$probeTask, $nullTask]));

        $command = new DebugProbeCommand($probe, $scheduler);

        $tester = new CommandTester($command);
        $tester->execute([
            '--external' => true,
        ]);

        self::assertStringContainsString('[INFO] Found 1 external probe', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringContainsString('Path', $tester->getDisplay());
        self::assertStringContainsString('/_external_path', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString(TaskInterface::PAUSED, $tester->getDisplay());
        self::assertStringContainsString('Last execution', $tester->getDisplay());
        self::assertStringContainsString($executionDate->format(DateTimeInterface::COOKIE), $tester->getDisplay());
        self::assertStringContainsString('Execution state', $tester->getDisplay());
        self::assertStringContainsString(TaskInterface::SUCCEED, $tester->getDisplay());
    }

    public function testCommandCanReturnMultipleExternalProbeState(): void
    {
        $probe = $this->createMock(ProbeInterface::class);

        $probeTask = new ProbeTask('foo', '/_external_path');
        $secondProbeTask = new ProbeTask('bar', '/_path');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([$probeTask, $secondProbeTask]));

        $command = new DebugProbeCommand($probe, $scheduler);

        $tester = new CommandTester($command);
        $tester->execute([
            '--external' => true,
        ]);

        self::assertStringContainsString('[INFO] Found 2 external probes', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringContainsString('bar', $tester->getDisplay());
        self::assertStringContainsString('Path', $tester->getDisplay());
        self::assertStringContainsString('/_external_path', $tester->getDisplay());
        self::assertStringContainsString('/_path', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString('enabled', $tester->getDisplay());
        self::assertStringContainsString('enabled', $tester->getDisplay());
        self::assertStringContainsString('Last execution', $tester->getDisplay());
        self::assertStringContainsString('Not executed', $tester->getDisplay());
        self::assertStringContainsString('Not executed', $tester->getDisplay());
        self::assertStringContainsString('Execution state', $tester->getDisplay());
        self::assertStringContainsString('Not executed', $tester->getDisplay());
        self::assertStringContainsString('Not executed', $tester->getDisplay());
    }
}
