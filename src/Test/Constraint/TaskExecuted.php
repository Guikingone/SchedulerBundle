<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskExecuted extends Constraint
{
    private $expectedCount;

    public function __construct(int $expectedCount)
    {
        $this->expectedCount = $expectedCount;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return \sprintf('%s %s been executed', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param TaskEventList $eventsList
     *
     * {@inheritdoc}
     */
    protected function matches($eventsList): bool
    {
        return $this->expectedCount === $this->countExecutedTasks($eventsList);
    }

    private function countExecutedTasks(TaskEventList $eventsList): int
    {
        $count = 0;
        foreach ($eventsList->getEvents() as $event) {
            if (!$event instanceof TaskExecutedEvent && TaskInterface::INCOMPLETE !== $event->getTask()->getExecutionState()) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
