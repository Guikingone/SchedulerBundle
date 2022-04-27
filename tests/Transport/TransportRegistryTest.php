<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\TransportRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportRegistryTest extends TestCase
{
    public function testRegistryCannotReturnFirstTransportWhenEmpty(): void
    {
        $registry = new TransportRegistry([]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The transport registry is empty');
        self::expectExceptionCode(0);
        $registry->reset();
    }

    public function testRegistryCanReturnFirstTransport(): void
    {
        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $registry = new TransportRegistry([
            $transport,
        ]);

        $firstTransport = $registry->reset();
        self::assertSame($firstTransport, $transport);
    }
}
