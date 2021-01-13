<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new InMemoryTransportFactory();

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('memory://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryReturnTransport(string $dsn): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new InMemoryTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);

        static::assertInstanceOf(InMemoryTransport::class, $transport);
        static::assertArrayHasKey('execution_mode', $transport->getOptions());
    }

    public function provideDsn(): \Generator
    {
        yield [
            'memory://batch',
            'memory://deadline',
            'memory://first_in_first_out',
            'memory://normal',
            'memory://normal?nice=10',
        ];
    }
}
