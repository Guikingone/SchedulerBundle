<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\ApiPlatform\Filter;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\ApiPlatform\Filter\SearchFilter;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use stdClass;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SearchFilterTest extends TestCase
{
    public function testFilterCanDefineDescription(): void
    {
        $filter = new SearchFilter();

        self::assertEmpty($filter->getDescription(stdClass::class));
        self::assertNotEmpty($filter->getDescription(TaskInterface::class));
    }

    public function testFilterDescriptionIsDefined(): void
    {
        $filter = new SearchFilter();

        $description = $filter->getDescription(TaskInterface::class);

        self::assertCount(5, $description);
        self::assertArrayHasKey('expression', $description);
        self::assertArrayHasKey('queued', $description);
        self::assertArrayHasKey('state', $description);
        self::assertArrayHasKey('timezone', $description);
        self::assertArrayHasKey('type', $description);
        self::assertCount(4, $description['expression']);
        self::assertCount(4, $description['queued']);
        self::assertCount(4, $description['state']);
        self::assertCount(4, $description['timezone']);
        self::assertCount(4, $description['type']);
        self::assertSame('string', $description['expression']['type']);
        self::assertFalse($description['expression']['required']);
        self::assertSame('expression', $description['expression']['property']);
        self::assertSame([
            'description' => 'Filter tasks using the expression',
            'name' => 'expression',
            'type' => 'string',
        ], $description['expression']['swagger']);
        self::assertSame('bool', $description['queued']['type']);
        self::assertFalse($description['queued']['required']);
        self::assertSame('queued', $description['queued']['property']);
        self::assertSame([
            'description' => 'Filter tasks that are queued',
            'name' => 'queued',
            'type' => 'bool',
        ], $description['queued']['swagger']);
        self::assertSame('string', $description['state']['type']);
        self::assertFalse($description['state']['required']);
        self::assertSame('state', $description['state']['property']);
        self::assertSame([
            'description' => 'Filter tasks with a specific state',
            'name' => 'state',
            'type' => 'string',
        ], $description['state']['swagger']);
        self::assertSame('string', $description['timezone']['type']);
        self::assertFalse($description['timezone']['required']);
        self::assertSame('timezone', $description['timezone']['property']);
        self::assertSame([
            'description' => 'Filter tasks scheduled using a specific timezone',
            'name' => 'timezone',
            'type' => 'string',
        ], $description['timezone']['swagger']);
        self::assertSame('string', $description['type']['type']);
        self::assertFalse($description['type']['required']);
        self::assertSame('type', $description['type']['property']);
        self::assertSame([
            'description' => 'Filter tasks depending on internal type',
            'name' => 'timezone',
            'type' => 'string',
        ], $description['type']['swagger']);
    }

    public function testFilterCannotFilterWithoutFilters(): void
    {
        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::never())->method('count');
        $list->expects(self::never())->method('filter');

        $filter = new SearchFilter();
        $filter->filter($list);
    }

    public function testFilterCannotFilterEmptyList(): void
    {
        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::once())->method('count')->willReturn(0);
        $list->expects(self::never())->method('filter');

        $filter = new SearchFilter();
        $filter->filter($list, [
            'expression' => '* * * * *',
        ]);
    }

    public function testFilterCanFilterOnExpression(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::never())->method('getState');
        $task->expects(self::never())->method('getTimezone');

        $filter = new SearchFilter();
        $list = $filter->filter(new TaskList([$task]), [
            'expression' => '* * * * *',
        ]);

        self::assertNotEmpty($list);
        self::assertCount(1, $list);
    }

    public function testFilterCanFilterOnQueuedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getExpression');
        $task->expects(self::once())->method('isQueued')->willReturn(true);
        $task->expects(self::never())->method('getState');
        $task->expects(self::never())->method('getTimezone');

        $filter = new SearchFilter();
        $list = $filter->filter(new TaskList([$task]), [
            'queued' => true,
        ]);

        self::assertNotEmpty($list);
        self::assertCount(1, $list);
    }

    public function testFilterCanFilterOnTaskState(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getExpression');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::SUCCEED);
        $task->expects(self::never())->method('getTimezone');

        $filter = new SearchFilter();
        $list = $filter->filter(new TaskList([$task]), [
            'state' => TaskInterface::SUCCEED,
        ]);

        self::assertNotEmpty($list);
        self::assertCount(1, $list);
    }

    public function testFilterCanFilterOnTaskTimezone(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getExpression');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::never())->method('getState');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));

        $filter = new SearchFilter();
        $list = $filter->filter(new TaskList([$task]), [
            'timezone' => 'UTC',
        ]);

        self::assertNotEmpty($list);
        self::assertCount(1, $list);
    }

    public function testFilterCanFilterOnTaskType(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getExpression');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::never())->method('getState');
        $task->expects(self::never())->method('getTimezone');

        $filter = new SearchFilter();
        $list = $filter->filter(new TaskList([new NullTask('foo')]), [
            'type' => NullTask::class,
        ]);

        self::assertNotEmpty($list);
        self::assertCount(1, $list);
    }
}
