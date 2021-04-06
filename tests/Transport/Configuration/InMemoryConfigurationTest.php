<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryConfigurationTest extends TestCase
{
    public function testConfigurationCanBeCreated(): void
    {
        $inMemoryConfiguration = new InMemoryConfiguration();

        $inMemoryConfiguration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->getOptions());
        self::assertSame('bar', $inMemoryConfiguration->get('foo'));
    }

    public function testConfigurationCanBeUpdated(): void
    {
        $inMemoryConfiguration = new InMemoryConfiguration();

        $inMemoryConfiguration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->getOptions());
        self::assertSame('bar', $inMemoryConfiguration->get('foo'));

        $inMemoryConfiguration->update('foo', 'foo_new');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->getOptions());
        self::assertSame('foo_new', $inMemoryConfiguration->get('foo'));
    }

    public function testConfigurationCanBeRemoved(): void
    {
        $inMemoryConfiguration = new InMemoryConfiguration();

        $inMemoryConfiguration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->getOptions());
        self::assertSame('bar', $inMemoryConfiguration->get('foo'));

        $inMemoryConfiguration->remove('foo');

        self::assertArrayNotHasKey('foo', $inMemoryConfiguration->getOptions());
        self::assertNull($inMemoryConfiguration->get('foo'));
    }
}
