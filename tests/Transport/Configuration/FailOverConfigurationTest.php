<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
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

    public function testTransportCanSetValue(): void
    {
        $transport = new FailOverConfiguration([
            new InMemoryConfiguration(),
        ]);

        $transport->set('foo', 'bar');

        self::assertSame('bar', $transport->get('foo'));
    }

    public function testTransportCanSetValueWithAFailingConfiguration(): void
    {
        $failingTransport = $this->createMock(ConfigurationInterface::class);
        $failingTransport->expects(self::once())->method('set')
            ->with(self::equalTo('foo'), self::equalTo('bar'))
            ->willThrowException(new InvalidArgumentException('An error occurred'))
        ;

        $transport = new FailOverConfiguration([
            $failingTransport,
            new InMemoryConfiguration(),
        ]);

        $transport->set('foo', 'bar');
        self::assertSame('bar', $transport->get('foo'));
    }

    public function testTransportCannotUpdateAValueWithoutConfigurations(): void
    {
        $transport = new FailOverConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $transport->update('foo', 'bar');
    }

    public function testTransportCanUpdateValue(): void
    {
        $transport = new FailOverConfiguration([
            new InMemoryConfiguration(),
        ]);

        $transport->set('foo', 'bar');
        self::assertSame('bar', $transport->get('foo'));

        $transport->update('foo', 'random');
        self::assertSame('random', $transport->get('foo'));
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

    public function testTransportCannotRemoveValueWithoutConfigurations(): void
    {
        $transport = new FailOverConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $transport->remove('foo');
    }

    public function testTransportCanRemoveValue(): void
    {
        $transport = new FailOverConfiguration([
            new InMemoryConfiguration(),
        ]);

        $transport->set('foo', 'bar');
        self::assertSame('bar', $transport->get('foo'));

        $transport->remove('foo');
        self::assertNull($transport->get('foo'));
    }

    public function testTransportCannotRetrieveOptionsWithoutConfigurations(): void
    {
        $transport = new FailOverConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $transport->getOptions();
    }

    public function testTransportCanRetrieveOptions(): void
    {
        $transport = new FailOverConfiguration([
            new InMemoryConfiguration(),
        ]);

        $transport->set('foo', 'bar');
        self::assertArrayHasKey('foo', $transport->getOptions());
        self::assertSame('bar', $transport->getOptions()['foo']);
    }
}
