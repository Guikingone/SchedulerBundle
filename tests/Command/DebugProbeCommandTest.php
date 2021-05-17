<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\DebugProbeCommand;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\SchedulerInterface;
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
        $probe = $this->createMock(ProbeInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $command = new DebugProbeCommand($probe, $scheduler);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('[INFO] The displayed probe state is the one found at', $tester->getDisplay());
        self::assertStringContainsString('Executed tasks', $tester->getDisplay());
        self::assertStringContainsString('0', $tester->getDisplay());
        self::assertStringContainsString('Failed tasks', $tester->getDisplay());
        self::assertStringContainsString('0', $tester->getDisplay());
        self::assertStringContainsString('Scheduled tasks', $tester->getDisplay());
        self::assertStringContainsString('0', $tester->getDisplay());
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
}
