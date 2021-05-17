<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\SchedulerInterface;
use Symfony\Component\Console\Command\Command;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugProbeCommand extends Command
{
    private ProbeInterface $probe;
    private SchedulerInterface $scheduler;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:debug:probe';

    public function __construct(
        ProbeInterface $probe,
        SchedulerInterface $scheduler
    ) {
        $this->probe = $probe;
        $this->scheduler = $scheduler;

        parent::__construct();
    }
}
