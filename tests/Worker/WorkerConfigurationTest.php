<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Worker\WorkerConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerConfigurationTest extends TestCase
{
    public function testConfigurationCanBeCreated(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertFalse($configuration->shouldStop());

        $configuration->stop();
        self::assertTrue($configuration->shouldStop());
    }
}
