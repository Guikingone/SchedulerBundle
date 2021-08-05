<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskLockRegistry;
use Symfony\Component\Lock\LockInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockRegistryTest extends TestCase
{
    public function testRegistryCannotAddExistingLock(): void
    {
        $lock = $this->createMock(LockInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The lock related to the task "foo" has been added'));
        $logger->expects(self::once())->method('warning')->with(self::equalTo('A lock already exist for the task "foo"'));

        $registry = new TaskLockRegistry($logger);
        $registry->add(new NullTask('foo'), $lock);
        $registry->add(new NullTask('foo'), $lock);
    }

    public function testRegistryCanAddLock(): void
    {
        $lock = $this->createMock(LockInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The lock related to the task "foo" has been added'));

        $registry = new TaskLockRegistry($logger);
        $registry->add(new NullTask('foo'), $lock);
    }

    public function testRegistryCannotReturnUndefinedLock(): void
    {
    }

    public function testRegistryCanReturnExistingLock(): void
    {
    }

    public function testRegistryCanRemoveLock(): void
    {
    }
}
