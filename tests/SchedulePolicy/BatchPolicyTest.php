<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BatchPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $batchPolicy = new BatchPolicy();

        self::assertFalse($batchPolicy->support('test'));
        self::assertTrue($batchPolicy->support('batch'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);
        $task->expects(self::once())->method('setPriority')->withConsecutive([1], [0]);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);
        $secondTask->expects(self::once())->method('setPriority')->withConsecutive([1], [0]);

        $batchPolicy = new BatchPolicy();
        $list = $batchPolicy->sort([$secondTask, $task]);

        self::assertCount(2, $list);
        self::assertEquals([$task, $secondTask], $list);
    }

    public function testTasksPriorityIsNotOutOfBounds(): void
    {
        $fooTask = new NullTask('foo', ['priority' => 1000]);
        $barTask = new NullTask('bar', ['priority' => -1000]);
        $bazTask = new NullTask('baz');

        $batchPolicy = new BatchPolicy();
        $batchPolicy->sort([$fooTask, $barTask, $bazTask]);

        self::assertEquals(1000, $fooTask->getPriority());
        self::assertEquals(-1, $bazTask->getPriority());
        self::assertEquals(-1000, $barTask->getPriority());
    }
}
