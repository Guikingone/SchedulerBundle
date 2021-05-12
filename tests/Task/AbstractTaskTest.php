<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\AbstractTask;

/**
 * @author Jérémy Vancoillie <contact@jeremyvancoillie.fr>
 */
final class AbstractTaskTest extends TestCase
{
    public function testTaskPrioritySetter(): void
    {
        $abstractTask = $this->getMockForAbstractClass(AbstractTask::class, ['name' => 'foo']);

        self::assertEquals(1000, $abstractTask->setPriority(1000)->getPriority());
        self::assertEquals(1000, $abstractTask->setPriority(1001)->getPriority());
        self::assertEquals(-1000, $abstractTask->setPriority(-1000)->getPriority());
        self::assertEquals(-1000, $abstractTask->setPriority(-1001)->getPriority());
        self::assertEquals(5, $abstractTask->setPriority(5)->getPriority());
    }
}
