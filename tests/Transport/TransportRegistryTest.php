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
        $registry = new TransportRegistry(transports: []);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The transport registry is empty');
        self::expectExceptionCode(0);
        $registry->reset();
    }

    public function testRegistryCanReturnFirstTransport(): void
    {
        $transport = new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ]));

        $registry = new TransportRegistry(transports: [
            $transport,
        ]);

        $firstTransport = $registry->reset();
        self::assertSame($firstTransport, $transport);
    }
}
