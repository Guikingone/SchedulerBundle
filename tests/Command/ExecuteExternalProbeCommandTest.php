<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SchedulerBundle\Command\ExecuteExternalProbeCommand;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecuteExternalProbeCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $command = new ExecuteExternalProbeCommand($scheduler, $worker);

        self::assertSame('scheduler:execute:external-probe', $command->getName());
        self::assertSame('Execute the external probes', $command->getDescription());
    }

    public function testCommandCannotExecuteEmptyProbeTasks(): void
    {
        $nullTask = new NullTask('foo');

        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$nullTask]));

        $command = new ExecuteExternalProbeCommand($scheduler, $worker);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('No external probe found', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testCommandCannotExecuteProbeTasksWithExecutionError(): void
    {
        $probeTask = new ProbeTask('foo', 'https://www.foo.com/_probe');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$probeTask]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')
            ->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($probeTask))
            ->willThrowException(new RuntimeException('Tasks execution error message'))
        ;

        $command = new ExecuteExternalProbeCommand($scheduler, $worker);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringNotContainsString('No external probe found', $tester->getDisplay());
        self::assertStringContainsString('An error occurred during the external probe execution:', $tester->getDisplay());
        self::assertStringContainsString('Tasks execution error message', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testCommandCanExecuteProbeTasks(): void
    {
        $probeTask = new ProbeTask('foo_probe', 'https://www.foo.com/_probe');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$probeTask]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')
            ->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($probeTask))
        ;

        $command = new ExecuteExternalProbeCommand($scheduler, $worker);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringNotContainsString('No external probe found', $tester->getDisplay());
        self::assertStringNotContainsString('An error occurred during the external probe execution:', $tester->getDisplay());
        self::assertStringContainsString('1 external probe executed', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo_probe', $tester->getDisplay());
        self::assertStringContainsString('Path', $tester->getDisplay());
        self::assertStringContainsString('https://www.foo.com/_probe', $tester->getDisplay());
        self::assertStringContainsString('Delay', $tester->getDisplay());
        self::assertStringContainsString('0', $tester->getDisplay());
        self::assertStringContainsString('Execution state', $tester->getDisplay());
        self::assertStringNotContainsString(TaskInterface::SUCCEED, $tester->getDisplay());
        self::assertStringContainsString('Not executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCommandCanExecuteMultipleProbeTasks(): void
    {
        $probeTask = new ProbeTask('foo', 'https://www.foo.com/_probe');
        $secondProbeTasks = new ProbeTask('bar', 'https://www.bar.com/_probe');
        $secondProbeTasks->setExecutionState(TaskInterface::SUCCEED);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$probeTask, $secondProbeTasks]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')
            ->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($probeTask))
        ;

        $command = new ExecuteExternalProbeCommand($scheduler, $worker);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringNotContainsString('No external probe found', $tester->getDisplay());
        self::assertStringNotContainsString('An error occurred during the external probe execution:', $tester->getDisplay());
        self::assertStringContainsString('2 external probes executed', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringContainsString('bar', $tester->getDisplay());
        self::assertStringContainsString('Path', $tester->getDisplay());
        self::assertStringContainsString('https://www.foo.com/_probe', $tester->getDisplay());
        self::assertStringContainsString('https://www.bar.com/_probe', $tester->getDisplay());
        self::assertStringContainsString('Delay', $tester->getDisplay());
        self::assertStringContainsString('0', $tester->getDisplay());
        self::assertStringContainsString('Execution state', $tester->getDisplay());
        self::assertStringContainsString('Not executed', $tester->getDisplay());
        self::assertStringContainsString(TaskInterface::SUCCEED, $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
