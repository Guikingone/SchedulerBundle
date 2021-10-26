<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Pool\Configuration;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerConfigurationTest extends TestCase
{
    public function testConfigurationReturnInformation(): void
    {
        $configuration = new SchedulerConfiguration(new DateTimeZone('UTC'), new DateTimeImmutable(), new NullTask('foo'));

        self::assertSame('UTC', $configuration->getTimezone()->getName());
        self::assertCount(1, $configuration->getDueTasks());
    }
}
