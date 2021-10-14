<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Redis\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Redis\Transport\RedisTransportFactory;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function getenv;
use function is_bool;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class RedisTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        self::assertFalse((new RedisTransportFactory())->support('test://'));
        self::assertTrue((new RedisTransportFactory())->support('redis://'));
    }

    public function testTransportCanBeBuilt(): void
    {
        $redisDsn = getenv('SCHEDULER_REDIS_DSN');
        if (is_bool($redisDsn)) {
            self::markTestSkipped('The "SCHEDULER_REDIS_DSN" environment variable is required.');
        }

        $dsn = Dsn::fromString($redisDsn);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new RedisTransportFactory();
        $transport = $factory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertSame($dsn->getHost(), $transport->getOptions()['host']);
        self::assertSame($dsn->getPassword(), $transport->getOptions()['password']);
        self::assertSame($dsn->getPort(), $transport->getOptions()['port']);
        self::assertSame($dsn->getScheme(), $transport->getOptions()['scheme']);
        self::assertSame($dsn->getOption('timeout', 30), $transport->getOptions()['timeout']);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('list', $transport->getOptions());
        self::assertSame('_symfony_scheduler_tasks', $transport->getOptions()['list']);
    }
}
