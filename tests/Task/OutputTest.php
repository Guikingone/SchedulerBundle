<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class OutputTest extends TestCase
{
    public function testOutputCanBeCreatedForSuccess(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $output = new Output($task);

        self::assertSame('undefined', $output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame('success', $output->getType());
    }

    public function testOutputCanBeCreatedForError(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $output = new Output($task, null, Output::ERROR);

        self::assertNull($output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame('error', $output->getType());
    }
}
