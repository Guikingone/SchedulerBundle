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

        self::assertSame(expected: 'scheduler:yield', actual: $yieldTaskCommand->getName());
        self::assertSame(expected: 'Yield a task', actual: $yieldTaskCommand->getDescription());
        self::assertTrue(condition: $yieldTaskCommand->getDefinition()->hasArgument(name: 'name'));
        self::assertSame(expected: 'The task to yield', actual: $yieldTaskCommand->getDefinition()->getArgument(name: 'name')->getDescription());
        self::assertTrue(condition: $yieldTaskCommand->getDefinition()->getArgument(name: 'name')->isRequired());
        self::assertTrue(condition: $yieldTaskCommand->getDefinition()->hasOption(name: 'async'));
        self::assertSame(expected: 'Yield the task using the message bus', actual: $yieldTaskCommand->getDefinition()->getOption(name: 'async')->getDescription());
        self::assertSame(expected: 'a', actual: $yieldTaskCommand->getDefinition()->getOption(name: 'async')->getShortcut());
        self::assertTrue(condition: $yieldTaskCommand->getDefinition()->hasOption(name: 'force'));
        self::assertSame(expected: 'Force the operation without confirmation', actual: $yieldTaskCommand->getDefinition()->getOption(name: 'force')->getDescription());
        self::assertSame(expected: 'f', actual: $yieldTaskCommand->getDefinition()->getOption(name: 'force')->getShortcut());
        self::assertSame(
            expected:
            $yieldTaskCommand->getHelp(),
            actual:
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
        $scheduler->schedule(task: new NullTask(name: 'foo'));
        $scheduler->schedule(task: new NullTask(name: 'bar'));

        $tester = new CommandCompletionTester(command: new YieldTaskCommand(scheduler: $scheduler));
        $suggestions = $tester->complete(input: ['f', 'b']);

        self::assertSame(expected: ['foo', 'bar'], actual: $suggestions);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCannotYieldWithoutConfirmationOrForceOption(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));

        $commandTester = new CommandTester(command: new YieldTaskCommand(scheduler: $scheduler));
        $commandTester->execute(input: [
            'name' => 'foo',
        ]);

        self::assertSame(expected: Command::FAILURE, actual: $commandTester->getStatusCode());
        self::assertStringContainsString(needle: '[WARNING] The task "foo" has not been yielded', haystack: $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCanYieldWithConfirmation(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));
        $scheduler->schedule(task: new NullTask(name: 'foo'));

        $commandTester = new CommandTester(command: new YieldTaskCommand(scheduler: $scheduler));
        $commandTester->setInputs(inputs: ['yes']);
        $commandTester->execute(input: [
            'name' => 'foo',
        ]);

        self::assertSame(expected: Command::SUCCESS, actual: $commandTester->getStatusCode());
        self::assertStringContainsString(needle: '[OK] The task "foo" has been yielded', haystack: $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCanYieldWithForceOption(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));
        $scheduler->schedule(task: new NullTask(name: 'foo'));

        $commandTester = new CommandTester(command: new YieldTaskCommand(scheduler: $scheduler));
        $commandTester->execute(input: [
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(expected: Command::SUCCESS, actual: $commandTester->getStatusCode());
        self::assertStringContainsString(needle: '[OK] The task "foo" has been yielded', haystack: $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testCommandCanYieldUsingAsyncOption(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore()));
        $scheduler->schedule(task: new NullTask(name: 'foo'));

        $commandTester = new CommandTester(command: new YieldTaskCommand(scheduler: $scheduler));
        $commandTester->execute(input: [
            'name' => 'foo',
            '--async' => true,
            '--force' => true,
        ]);

        self::assertSame(expected: Command::SUCCESS, actual: $commandTester->getStatusCode());
        self::assertStringContainsString(needle: '[OK] The task "foo" has been yielded', haystack: $commandTester->getDisplay());
    }
}
