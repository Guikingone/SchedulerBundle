<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryConfigurationTest extends TestCase
{
    public function testTransportCannotBeConfiguredWithInvalidOptionType(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_mode" with value 350 is expected to be of type "string" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        $configuration = new InMemoryConfiguration();
        $configuration->init(['execution_mode' => 350]);
    }

    public function testConfigurationCanBeCreated(): void
    {
        $inMemoryConfiguration = new InMemoryConfiguration();

        $inMemoryConfiguration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->toArray());
        self::assertSame('bar', $inMemoryConfiguration->get('foo'));
    }

    public function testConfigurationCanBeUpdated(): void
    {
        $inMemoryConfiguration = new InMemoryConfiguration();

        $inMemoryConfiguration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->toArray());
        self::assertSame('bar', $inMemoryConfiguration->get('foo'));

        $inMemoryConfiguration->update('foo', 'foo_new');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->toArray());
        self::assertSame('foo_new', $inMemoryConfiguration->get('foo'));
    }

    public function testConfigurationCanBeRemoved(): void
    {
        $inMemoryConfiguration = new InMemoryConfiguration();

        $inMemoryConfiguration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $inMemoryConfiguration->toArray());
        self::assertSame('bar', $inMemoryConfiguration->get('foo'));

        $inMemoryConfiguration->remove('foo');

        self::assertArrayNotHasKey('foo', $inMemoryConfiguration->toArray());
        self::assertNull($inMemoryConfiguration->get('foo'));
    }
}
