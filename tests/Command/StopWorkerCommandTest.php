<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\Command\StopWorkerCommand;
use SchedulerBundle\EventListener\StopWorkerOnNextTaskSubscriber;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $command = new StopWorkerCommand(stopWorkerCacheItemPool: new ArrayAdapter());

        self::assertSame('scheduler:stop-worker', $command->getName());
        self::assertSame('Stops the worker after the current task', $command->getDescription());
        self::assertCount(0, $command->getDefinition()->getArguments());
        self::assertCount(0, $command->getDefinition()->getOptions());
        self::assertSame(
            $command->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command stop the worker after the current task.

                    <info>php %command.full_name%</info>

                The worker will *not* be restarted once stopped.
                EOF
        );
    }

    public function testCommandCannotTriggerWorkerStopWithAnErrorOnCacheItemPool(): void
    {
        $adapter = $this->createMock(CacheItemPoolInterface::class);
        $adapter->expects(self::once())->method('getItem')->willThrowException(new InvalidArgumentException('Error'));

        $command = new StopWorkerCommand(stopWorkerCacheItemPool: $adapter);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('An error occurred while trying to stop the worker:', $tester->getDisplay());
        self::assertStringContainsString('Error', $tester->getDisplay());
        self::assertStringNotContainsString('The worker will be stopped after executing the current task or once the sleep phase is over', $tester->getDisplay());
    }

    public function testCommandCanTriggerWorkerStop(): void
    {
        $adapter = new ArrayAdapter();

        $command = new StopWorkerCommand(stopWorkerCacheItemPool: $adapter);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('An error occurred while trying to stop the worker:', $tester->getDisplay());
        self::assertStringContainsString('The worker will be stopped after executing the current task or once the sleep phase is over', $tester->getDisplay());
        self::assertTrue($adapter->getItem(StopWorkerOnNextTaskSubscriber::STOP_NEXT_TASK_TIMESTAMP_KEY)->isHit());
        self::assertIsFloat($adapter->getItem(StopWorkerOnNextTaskSubscriber::STOP_NEXT_TASK_TIMESTAMP_KEY)->get());
    }
}
