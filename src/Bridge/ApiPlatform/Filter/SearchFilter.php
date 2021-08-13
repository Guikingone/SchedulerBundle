<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\ApiPlatform\Filter;

use ApiPlatform\Core\Api\FilterInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use function get_class;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SearchFilter implements FilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass): array
    {
        if (TaskInterface::class !== $resourceClass) {
            return [];
        }

        return [
            'expression' => [
                'type' => 'string',
                'required' => false,
                'property' => 'expression',
                'swagger' => [
                    'description' => 'Filter tasks using the expression',
                    'name' => 'expression',
                    'type' => 'string',
                ],
            ],
            'queued' => [
                'type' => 'bool',
                'required' => false,
                'property' => 'queued',
                'swagger' => [
                    'description' => 'Filter tasks that are queued',
                    'name' => 'queued',
                    'type' => 'bool',
                ],
            ],
            'state' => [
                'type' => 'string',
                'required' => false,
                'property' => 'state',
                'swagger' => [
                    'description' => 'Filter tasks with a specific state',
                    'name' => 'state',
                    'type' => 'string',
                ],
            ],
            'timezone' => [
                'type' => 'string',
                'required' => false,
                'property' => 'timezone',
                'swagger' => [
                    'description' => 'Filter tasks scheduled using a specific timezone',
                    'name' => 'timezone',
                    'type' => 'string',
                ],
            ],
            'type' => [
                'type' => 'string',
                'required' => false,
                'property' => 'type',
                'swagger' => [
                    'description' => 'Filter tasks depending on internal type',
                    'name' => 'timezone',
                    'type' => 'string',
                ],
            ],
        ];
    }

    public function filter(TaskListInterface $list, array $filters = []): TaskListInterface
    {
        if ([] === $filters) {
            return $list;
        }

        if (0 === $list->count()) {
            return $list;
        }

        foreach ($filters as $filter => $value) {
            switch ($filter) {
                case 'expression':
                    $list = $list->filter(fn (TaskInterface $task): bool => $value === $task->getExpression());
                    break;
                case 'queued':
                    $list = $list->filter(fn (TaskInterface $task): bool => $task->isQueued());
                    break;
                case 'state':
                    $list = $list->filter(fn (TaskInterface $task): bool => $value === $task->getState());
                    break;
                case 'timezone':
                    $list = $list->filter(function (TaskInterface $task) use ($value): bool {
                        $timezone = $task->getTimezone();

                        return null !== $timezone && $value === $timezone->getName();
                    });
                    break;
                case 'type':
                    $list = $list->filter(fn (TaskInterface $task): bool => $value === get_class($task));
                    break;
            }
        }

        return $list;
    }
}
