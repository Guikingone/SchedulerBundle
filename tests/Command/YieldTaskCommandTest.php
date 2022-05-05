<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\YieldTaskCommand;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class YieldTaskCommandTest extends TestCase
{
    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandIsConfigured(): void
    {
        $yieldTaskCommand = new YieldTaskCommand(scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore())));

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

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testCommandCanSuggestStoredTasks(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));
        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));

        $tester = new CommandCompletionTester(new YieldTaskCommand($scheduler));
        $suggestions = $tester->complete(['f', 'b']);

        self::assertSame(['foo', 'bar'], $suggestions);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCannotYieldWithoutConfirmationOrForceOption(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));

        $commandTester = new CommandTester(new YieldTaskCommand($scheduler));
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] The task "foo" has not been yielded', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCanYieldWithConfirmation(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));

        $commandTester = new CommandTester(new YieldTaskCommand($scheduler));
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCanYieldWithForceOption(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));

        $commandTester = new CommandTester(new YieldTaskCommand($scheduler));
        $commandTester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been yielded', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCanYieldUsingAsyncOption(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));

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
