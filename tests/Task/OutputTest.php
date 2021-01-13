<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        static::assertSame('undefined', $output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame('success', $output->getType());
    }

    public function testOutputCanBeCreatedForError(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $output = new Output($task, null, Output::ERROR);

        static::assertNull($output->getOutput());
        static::assertSame($task, $output->getTask());
        static::assertSame('error', $output->getType());
    }
}
