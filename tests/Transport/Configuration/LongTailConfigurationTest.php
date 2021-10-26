<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Configuration\LongTailConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailConfigurationTest extends TestCase
{
    public function testTransportCannotSetAValueWithoutConfigurations(): void
    {
        $configuration = new LongTailConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $configuration->set('foo', 'bar');
    }

    public function testTransportCanSetValue(): void
    {
        $configuration = new LongTailConfiguration([
            new InMemoryConfiguration(),
        ]);

        $configuration->set('foo', 'bar');

        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testTransportCanSetValueWithAHighLoadedConfiguration(): void
    {
        $loadedConfiguration = $this->createMock(ConfigurationInterface::class);
        $loadedConfiguration->expects(self::exactly(2))->method('count')->willReturn(10);

        $configuration = new LongTailConfiguration([
            $loadedConfiguration,
            new InMemoryConfiguration(),
        ]);

        $configuration->set('foo', 'bar');
        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testTransportCannotUpdateAValueWithoutConfigurations(): void
    {
        $configuration = new LongTailConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $configuration->update('foo', 'bar');
    }

    public function testTransportCanUpdateValue(): void
    {
        $configuration = new LongTailConfiguration([
            new InMemoryConfiguration(),
        ]);

        $configuration->set('foo', 'bar');
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->update('foo', 'random');
        self::assertSame('random', $configuration->get('foo'));
    }

    public function testTransportCannotRetrieveValueWithoutConfigurations(): void
    {
        $configuration = new LongTailConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $configuration->get('foo');
    }

    public function testTransportCanRetrieveValue(): void
    {
        $configuration = new LongTailConfiguration([
            new InMemoryConfiguration(),
        ]);

        self::assertNull($configuration->get('foo'));

        $configuration->set('foo', 'bar');

        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testTransportCannotRemoveValueWithoutConfigurations(): void
    {
        $configuration = new LongTailConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $configuration->remove('foo');
    }

    public function testTransportCanRemoveValue(): void
    {
        $configuration = new LongTailConfiguration([
            new InMemoryConfiguration(),
        ]);

        $configuration->set('foo', 'bar');
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->remove('foo');
        self::assertNull($configuration->get('foo'));
    }

    public function testTransportCannotRetrieveOptionsWithoutConfigurations(): void
    {
        $configuration = new LongTailConfiguration([]);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('No configuration found');
        self::expectExceptionCode(0);
        $configuration->toArray();
    }

    public function testTransportCanRetrieveOptions(): void
    {
        $configuration = new LongTailConfiguration([
            new InMemoryConfiguration(),
        ]);

        $configuration->set('foo', 'bar');
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('bar', $configuration->toArray()['foo']);
    }
}
