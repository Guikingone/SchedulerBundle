<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Transport\Configuration\FailOverConfiguration;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverConfigurationTest extends TestCase
{
    public function testTransportCannotSetAValueWithoutConfigurations(): void
    {
        $transport = new FailOverConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $transport->set('foo', 'bar');
    }

    public function testTransportCannotRetrieveValueWithoutConfigurations(): void
    {
        $transport = new FailOverConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $transport->get('foo');
    }

    public function testTransportCanRetrieveValue(): void
    {
        $transport = new FailOverConfiguration([
            new InMemoryConfiguration(),
        ]);

        self::assertNull($transport->get('foo'));

        $transport->set('foo', 'bar');

        self::assertSame('bar', $transport->get('foo'));
    }
}
