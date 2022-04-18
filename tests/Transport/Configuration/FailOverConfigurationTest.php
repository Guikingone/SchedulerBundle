<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationRegistry;
use SchedulerBundle\Transport\Configuration\FailOverConfiguration;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverConfigurationTest extends TestCase
{
    public function testTransportCannotSetAValueWithoutConfigurations(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([]));

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $failOverConfiguration->set('foo', 'bar');
    }

    public function testTransportCanSetValue(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([
            new InMemoryConfiguration(),
        ]));

        $failOverConfiguration->set('foo', 'bar');

        self::assertSame('bar', $failOverConfiguration->get('foo'));
    }

    public function testTransportCanSetValueWithAFailingConfiguration(): void
    {
        $failingConfiguration = $this->createMock(ConfigurationInterface::class);
        $failingConfiguration->expects(self::once())->method('set')
            ->with(self::equalTo('foo'), self::equalTo('bar'))
            ->willThrowException(new InvalidArgumentException('An error occurred'))
        ;

        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([
            $failingConfiguration,
            new InMemoryConfiguration(),
        ]));

        $failOverConfiguration->set('foo', 'bar');
        self::assertSame('bar', $failOverConfiguration->get('foo'));
    }

    public function testTransportCannotUpdateAValueWithoutConfigurations(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([]));

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $failOverConfiguration->update('foo', 'bar');
    }

    public function testTransportCanUpdateValue(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([
            new InMemoryConfiguration(),
        ]));

        $failOverConfiguration->set('foo', 'bar');
        self::assertSame('bar', $failOverConfiguration->get('foo'));

        $failOverConfiguration->update('foo', 'random');
        self::assertSame('random', $failOverConfiguration->get('foo'));
    }

    public function testTransportCannotRetrieveValueWithoutConfigurations(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([]));

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $failOverConfiguration->get('foo');
    }

    public function testTransportCanRetrieveValue(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([
            new InMemoryConfiguration(),
        ]));

        self::assertNull($failOverConfiguration->get('foo'));

        $failOverConfiguration->set('foo', 'bar');

        self::assertSame('bar', $failOverConfiguration->get('foo'));
    }

    public function testTransportCannotRemoveValueWithoutConfigurations(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([]));

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $failOverConfiguration->remove('foo');
    }

    public function testTransportCanRemoveValue(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([
            new InMemoryConfiguration(),
        ]));

        $failOverConfiguration->set('foo', 'bar');
        self::assertSame('bar', $failOverConfiguration->get('foo'));

        $failOverConfiguration->remove('foo');
        self::assertNull($failOverConfiguration->get('foo'));
    }

    public function testTransportCannotRetrieveOptionsWithoutConfigurations(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([]));

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $failOverConfiguration->toArray();
    }

    public function testTransportCanRetrieveOptions(): void
    {
        $failOverConfiguration = new FailOverConfiguration(new ConfigurationRegistry([
            new InMemoryConfiguration(),
        ]));

        $failOverConfiguration->set('foo', 'bar');
        self::assertArrayHasKey('foo', $failOverConfiguration->toArray());
        self::assertSame('bar', $failOverConfiguration->toArray()['foo']);
    }
}
