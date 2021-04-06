<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\YieldTaskCommand;
use SchedulerBundle\SchedulerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class YieldTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $yieldTaskCommand = new YieldTaskCommand($scheduler);

        self::assertSame('scheduler:yield', $yieldTaskCommand->getName());
        self::assertSame('Yield a task', $yieldTaskCommand->getDescription());
        self::assertTrue($yieldTaskCommand->getDefinition()->hasArgument('name'));
        self::assertSame('The task to yield', $yieldTaskCommand->getDefinition()->getArgument('name')->getDescription());
        self::assertTrue($yieldTaskCommand->getDefinition()->getArgument('name')->isRequired());
        self::assertTrue($yieldTaskCommand->getDefinition()->hasOption('async'));
        self::assertSame('Yield the task using the message bus', $yieldTaskCommand->getDefinition()->getOption('async')->getDescription());
        self::assertSame('a', $yieldTaskCommand->getDefinition()->getOption('async')->getShortcut());
        self::assertTrue($yieldTaskCommand->getDefinition()->hasOption('force'));
        self::assertSame('Force the operation without confirmation', $yieldTaskCommand->getDefinition()->getOption('force')->getDescription());
        self::assertSame('f', $yieldTaskCommand->getDefinition()->getOption('force')->getShortcut());
        self::assertSame(
            $yieldTaskCommand->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command yield a task.

                    <info>php %command.full_name%</info>

                Use the name argument to specify the task to yield:
                    <info>php %command.full_name% <name></info>

                Use the --async option to perform the yield using the message bus:
                    <info>php %command.full_name% <name> --async</info>

                Use the --force option to force the task yield without asking for confirmation:
                    <info>php %command.full_name% <name> --force</info>
                EOF
        );
    }

    public function testCommandCannotYieldWithoutConfirmationOrForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('yieldTask');

        $commandTester = new CommandTester(new YieldTaskCommand($scheduler));
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] The task "foo" has not been yielded', $commandTester->getDisplay());
    }

    public function testCommandCanYieldWithConfirmation(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('yieldTask')->with(self::equalTo('foo'), self::equalTo(false));

        $commandTester = new CommandTester(new YieldTaskCommand($scheduler));
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $commandTester->getDisplay());
    }

    public function testCommandCanYieldWithForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('yieldTask')->with(self::equalTo('foo'), self::equalTo(false));

        $commandTester = new CommandTester(new YieldTaskCommand($scheduler));
        $commandTester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $commandTester->getDisplay());
    }

    public function testCommandCanYieldUsingAsyncOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('yieldTask')->with(self::equalTo('foo'), self::equalTo(true));

        $commandTester = new CommandTester(new YieldTaskCommand($scheduler));
        $commandTester->execute([
            'name' => 'foo',
            '--async' => true,
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $commandTester->getDisplay());
    }
}
