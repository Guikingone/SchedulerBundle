<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\DebugMiddlewareCommand;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\SchedulerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tests\SchedulerBundle\Command\Assets\PostExecutionMiddleware;
use Tests\SchedulerBundle\Command\Assets\PostSchedulingMiddleware;
use Tests\SchedulerBundle\Command\Assets\PreExecutionMiddleware;
use Tests\SchedulerBundle\Command\Assets\RequiredPreSchedulingMiddleware;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugMiddlewareCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $command = new DebugMiddlewareCommand(new SchedulerMiddlewareStack([]), new WorkerMiddlewareStack([]));

        self::assertSame('scheduler:debug:middleware', $command->getName());
        self::assertSame('Display the registered middlewares', $command->getDescription());
    }

    public function testCommandCanDisplayWarningWithEmptySchedulingPhaseMiddleware(): void
    {
        $command = new DebugMiddlewareCommand(new SchedulerMiddlewareStack([]), new WorkerMiddlewareStack([]));

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('[WARNING] No middleware found for the scheduling phase', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testCommandCanDisplayWarningWithEmptyExecutionPhaseMiddleware(): void
    {
        $command = new DebugMiddlewareCommand(new SchedulerMiddlewareStack([]), new WorkerMiddlewareStack([]));

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('[WARNING] No middleware found for the execution phase', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testCommandCanDisplaySchedulingPhaseMiddlewareList(): void
    {
        $command = new DebugMiddlewareCommand(new SchedulerMiddlewareStack([
            new NotifierMiddleware(),
            new TaskCallbackMiddleware(),
            new RequiredPreSchedulingMiddleware(),
            new PostSchedulingMiddleware(),
        ]), new WorkerMiddlewareStack([]));

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('[INFO] Found 4 middleware for the scheduling phase', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No middleware found for the scheduling phase', $tester->getDisplay());
        self::assertStringContainsString('[WARNING] No middleware found for the execution phase', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('TaskCallbackMiddleware', $tester->getDisplay());
        self::assertStringContainsString('NotifierMiddleware', $tester->getDisplay());
        self::assertStringContainsString('PreScheduling', $tester->getDisplay());
        self::assertStringContainsString('Yes', $tester->getDisplay());
        self::assertStringContainsString('PostScheduling', $tester->getDisplay());
        self::assertStringContainsString('Yes', $tester->getDisplay());
        self::assertStringContainsString('Priority', $tester->getDisplay());
        self::assertStringContainsString('1', $tester->getDisplay());
        self::assertStringContainsString('Required', $tester->getDisplay());
        self::assertStringContainsString('No', $tester->getDisplay());
        self::assertStringContainsString('| TaskCallbackMiddleware          | Yes           | Yes            | 1        | No       |', $tester->getDisplay());
        self::assertStringContainsString('| NotifierMiddleware              | Yes           | Yes            | 2        | No       |', $tester->getDisplay());
        self::assertStringContainsString('| RequiredPreSchedulingMiddleware | Yes           | No             | No       | Yes      |', $tester->getDisplay());
        self::assertStringContainsString('| PostSchedulingMiddleware        | No            | Yes            | No       | No       |', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testCommandCanDisplayExecutionPhaseMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $command = new DebugMiddlewareCommand(new SchedulerMiddlewareStack([]), new WorkerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new TaskLockBagMiddleware(new LockFactory(new InMemoryStore())),
            new SingleRunTaskMiddleware($scheduler),
            new PostExecutionMiddleware(),
            new PreExecutionMiddleware(),
        ]));

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('[INFO] Found 5 middleware for the execution phase', $tester->getDisplay());
        self::assertStringContainsString('[WARNING] No middleware found for the scheduling phase', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No middleware found for the execution phase', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('TaskCallbackMiddleware', $tester->getDisplay());
        self::assertStringContainsString('TaskLockBagMiddleware', $tester->getDisplay());
        self::assertStringContainsString('PreExecution', $tester->getDisplay());
        self::assertStringContainsString('Yes', $tester->getDisplay());
        self::assertStringContainsString('PostExecution', $tester->getDisplay());
        self::assertStringContainsString('Yes', $tester->getDisplay());
        self::assertStringContainsString('Priority', $tester->getDisplay());
        self::assertStringContainsString('1', $tester->getDisplay());
        self::assertStringContainsString('Required', $tester->getDisplay());
        self::assertStringContainsString('No', $tester->getDisplay());
        self::assertStringContainsString('| TaskCallbackMiddleware  | Yes          | Yes           | 1        | No       |', $tester->getDisplay());
        self::assertStringContainsString('| TaskLockBagMiddleware   | No           | Yes           | 5        | No       |', $tester->getDisplay());
        self::assertStringContainsString('| SingleRunTaskMiddleware | No           | Yes           | 15       | Yes      |', $tester->getDisplay());
        self::assertStringContainsString('| PostExecutionMiddleware | No           | Yes           | No       | No       |', $tester->getDisplay());
        self::assertStringContainsString('| PreExecutionMiddleware  | Yes          | No            | No       | No       |', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }
}
