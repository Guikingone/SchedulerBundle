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

        $command = new YieldTaskCommand($scheduler);

        self::assertSame('scheduler:yield', $command->getName());
        self::assertSame('Yield a task', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasArgument('name'));
        self::assertSame('The task to yield', $command->getDefinition()->getArgument('name')->getDescription());
        self::assertTrue($command->getDefinition()->getArgument('name')->isRequired());
        self::assertTrue($command->getDefinition()->hasOption('async'));
        self::assertSame('Yield the task using the message bus', $command->getDefinition()->getOption('async')->getDescription());
        self::assertSame('a', $command->getDefinition()->getOption('async')->getShortcut());
        self::assertTrue($command->getDefinition()->hasOption('force'));
        self::assertSame('Force the operation without confirmation', $command->getDefinition()->getOption('force')->getDescription());
        self::assertSame('f', $command->getDefinition()->getOption('force')->getShortcut());
        self::assertSame(
            $command->getHelp(),
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

        $tester = new CommandTester(new YieldTaskCommand($scheduler));
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] The task "foo" has not been yielded', $tester->getDisplay());
    }

    public function testCommandCanYieldWithConfirmation(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('yieldTask')->with(self::equalTo('foo'), self::equalTo(false));

        $tester = new CommandTester(new YieldTaskCommand($scheduler));
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $tester->getDisplay());
    }

    public function testCommandCanYieldWithForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('yieldTask')->with(self::equalTo('foo'), self::equalTo(false));

        $tester = new CommandTester(new YieldTaskCommand($scheduler));
        $tester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $tester->getDisplay());
    }

    public function testCommandCanYieldUsingAsyncOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('yieldTask')->with(self::equalTo('foo'), self::equalTo(true));

        $tester = new CommandTester(new YieldTaskCommand($scheduler));
        $tester->execute([
            'name' => 'foo',
            '--async' => true,
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $tester->getDisplay());
    }
}
