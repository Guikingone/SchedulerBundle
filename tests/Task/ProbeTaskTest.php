<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ProbeTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskTest extends TestCase
{
    public function testTaskCanBeConfiguredWithDefaultValues(): void
    {
        $task = new ProbeTask('foo', '/_probe');

        self::assertSame('foo', $task->getName());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertFalse($task->getErrorOnFailedTasks());
        self::assertSame(0, $task->getDelay());
    }

    public function testTaskCanBeConfigured(): void
    {
        $task = new ProbeTask('foo', '/_probe', true, 1000);

        self::assertSame('foo', $task->getName());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertTrue($task->getErrorOnFailedTasks());
        self::assertSame(1000, $task->getDelay());

        $task->setExternalProbePath('/_second_probe_path');
        $task->setErrorOnFailedTasks(false);
        $task->setDelay(2000);

        self::assertSame('/_second_probe_path', $task->getExternalProbePath());
        self::assertFalse($task->getErrorOnFailedTasks());
        self::assertSame(2000, $task->getDelay());
    }
}
