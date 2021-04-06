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

        $cacheTransportFactory = new CacheTransportFactory($pool);

        self::assertFalse($cacheTransportFactory->support('test://'));
        self::assertTrue($cacheTransportFactory->support('cache://'));
    }

    public function testFactoryCanCreateTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $orchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::once())->method('hasItem')->willReturn(true);

        $cacheTransportFactory = new CacheTransportFactory($pool);

        $transport = $cacheTransportFactory->createTransport(Dsn::fromString('cache://app?execution_mode=normal'), [], $serializer, $orchestrator);
        self::assertSame('normal', $transport->getOptions()['execution_mode']);
    }
}
