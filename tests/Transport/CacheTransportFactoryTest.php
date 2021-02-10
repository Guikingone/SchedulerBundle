<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\CacheTransportFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $factory = new CacheTransportFactory($pool);

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('cache://'));
    }

    public function testFactoryCanCreateTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $orchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::once())->method('hasItem')->willReturn(true);

        $factory = new CacheTransportFactory($pool);

        $transport = $factory->createTransport(Dsn::fromString('cache://first_in_first_out'), [], $serializer, $orchestrator);
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
    }
}
