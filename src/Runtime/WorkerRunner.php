<?php

declare(strict_types=1);

namespace SchedulerBundle\Runtime;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRunner implements RunnerInterface
{
    public function __construct(private WorkerInterface $worker) {}

    /**
     * {@inheritdoc}
     */
    public function run(): int
    {

    }
}
