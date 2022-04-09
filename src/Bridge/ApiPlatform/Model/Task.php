<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\ApiPlatform\Model;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use SchedulerBundle\Bridge\ApiPlatform\Filter\SearchFilter;
use SchedulerBundle\Task\AbstractTask;
use SchedulerBundle\Task\TaskInterface;

#[ApiResource(
    collectionOperations: ['get'],
    itemOperations: ['get'],
    routePrefix: '/tasks',
    stateless: true,
)]
#[ApiFilter(SearchFilter::class)]
final class Task extends AbstractTask
{
    public function __construct(private TaskInterface $wrappedTask)
    {
        parent::__construct(sprintf('%s.wrapped', $wrappedTask->getName()));
    }

    public function getWrappedTask(): TaskInterface
    {
        return $this->wrappedTask;
    }
}
