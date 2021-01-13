<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Redis\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Redis\Transport\RedisTransportFactory;

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
}
