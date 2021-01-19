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
        $configuration = new InMemoryConfiguration();

        $configuration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $configuration->getOptions());
        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testConfigurationCanBeUpdated(): void
    {
        $configuration = new InMemoryConfiguration();

        $configuration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $configuration->getOptions());
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->update('foo', 'foo_new');

        self::assertArrayHasKey('foo', $configuration->getOptions());
        self::assertSame('foo_new', $configuration->get('foo'));
    }

    public function testConfigurationCanBeRemoved(): void
    {
        $configuration = new InMemoryConfiguration();

        $configuration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $configuration->getOptions());
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->remove('foo');

        self::assertArrayNotHasKey('foo', $configuration->getOptions());
        self::assertNull($configuration->get('foo'));
    }
}
