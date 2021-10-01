<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Configuration\LazyConfiguration;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyConfigurationTest extends TestCase
{
    public function testConfigurationCanBeCreated(): void
    {
        $configuration = new LazyConfiguration(new InMemoryConfiguration());
        self::assertFalse($configuration->isInitialized());

        $configuration->set('foo', 'bar');
        self::assertTrue($configuration->isInitialized());

        self::assertSame(2, $configuration->count());
        self::assertArrayHasKey('execution_mode', $configuration->toArray());
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testConfigurationCannotBeUpdatedWithoutBeingInitialized(): void
    {
        $configuration = new LazyConfiguration(new InMemoryConfiguration());
        self::assertFalse($configuration->isInitialized());

        $configuration->update('foo', 'foo_new');
        self::assertTrue($configuration->isInitialized());
        self::assertArrayNotHasKey('foo', $configuration->toArray());
    }

    public function testConfigurationCanBeUpdatedWithSourceConfiguration(): void
    {
        $sourceConfiguration = new InMemoryConfiguration();
        $sourceConfiguration->set('foo', 'bar');

        $configuration = new LazyConfiguration($sourceConfiguration);
        self::assertFalse($configuration->isInitialized());

        $configuration->update('foo', 'foo_new');
        self::assertTrue($configuration->isInitialized());
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('foo_new', $configuration->get('foo'));
    }

    public function testConfigurationCanBeUpdated(): void
    {
        $configuration = new LazyConfiguration(new InMemoryConfiguration());
        self::assertFalse($configuration->isInitialized());

        $configuration->set('foo', 'bar');
        self::assertTrue($configuration->isInitialized());
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->update('foo', 'foo_new');
        self::assertTrue($configuration->isInitialized());
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('foo_new', $configuration->get('foo'));

        $configuration->update('foo', 'random');
        self::assertTrue($configuration->isInitialized());
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('random', $configuration->get('foo'));
    }

    public function testConfigurationCanBeRemoved(): void
    {
        $configuration = new LazyConfiguration(new InMemoryConfiguration());
        self::assertFalse($configuration->isInitialized());

        $configuration->set('foo', 'bar');
        self::assertTrue($configuration->isInitialized());

        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->remove('foo');

        self::assertArrayNotHasKey('foo', $configuration->toArray());
        self::assertNull($configuration->get('foo'));

        $configuration->remove('foo');

        self::assertArrayNotHasKey('foo', $configuration->toArray());
        self::assertNull($configuration->get('foo'));
    }

    public function testConfigurationCanMapValues(): void
    {
        $configuration = new LazyConfiguration(new InMemoryConfiguration());
        self::assertFalse($configuration->isInitialized());

        $configuration->set('foo', 'bar');
        $configuration->set('bar', 'foo');
        self::assertTrue($configuration->isInitialized());

        $mappedConfiguration = $configuration->map(static fn (string $value): string => sprintf('%s_value', $value));

        self::assertContains('bar_value', $mappedConfiguration);
        self::assertContains('foo_value', $mappedConfiguration);
    }
}
