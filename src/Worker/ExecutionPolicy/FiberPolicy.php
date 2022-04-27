<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberPolicy extends AbstractFiberHandler implements ExecutionPolicyInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function execute(
        TaskListInterface $toExecuteTasks,
        Closure $handleTaskFunc
    ): void {
        $toExecuteTasks->walk(func: function (TaskInterface $toExecuteTask) use ($toExecuteTasks, $handleTaskFunc): void {
            $this->handleOperationViaFiber(func: static function () use ($toExecuteTask, $toExecuteTasks, $handleTaskFunc): void {
                $handleTaskFunc($toExecuteTask, $toExecuteTasks);
            });
        });
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'fiber' === $policy;
    }
}
