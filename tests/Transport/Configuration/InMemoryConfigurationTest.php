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
        self::expectExceptionMessage('The option "execution_mode" with value 350 is expected to be of type "string", but is of type "int"');
        self::expectExceptionCode(0);
        $configuration = new InMemoryConfiguration();
        $configuration->init(['execution_mode' => 350]);
    }

    public function testConfigurationCanBeCreated(): void
    {
        $configuration = new InMemoryConfiguration();
        $configuration->set('foo', 'bar');

        self::assertSame(2, $configuration->count());
        self::assertArrayHasKey('execution_mode', $configuration->toArray());
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testConfigurationCanBeInitialized(): void
    {
        $configuration = new InMemoryConfiguration();
        $configuration->init([
            'execution_mode' => 'batch',
        ]);

        self::assertSame('batch', $configuration->get('execution_mode'));
    }

    public function testConfigurationCannotBeInitializedWithExtraOptionsAndInvalidTypes(): void
    {
        $configuration = new InMemoryConfiguration();

        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value 123 is expected to be of type "string", but is of type "int"');
        self::expectExceptionCode(0);
        $configuration->init([
            'execution_mode' => 'batch',
            'foo' => 123,
        ], [
            'foo' => 'string',
        ]);
    }

    public function testConfigurationCanBeInitializedWithExtraOptions(): void
    {
        $configuration = new InMemoryConfiguration();
        $configuration->init([
            'execution_mode' => 'batch',
            'foo' => 'bar',
        ], [
            'foo' => 'string',
        ]);

        self::assertSame('batch', $configuration->get('execution_mode'));
        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testConfigurationCanBeUpdated(): void
    {
        $inMemoryConfiguration = new InMemoryConfiguration();
        self::assertSame('first_in_first_out', $inMemoryConfiguration->get('execution_mode'));

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

    public function testConfigurationCanMapValues(): void
    {
        $configuration = new InMemoryConfiguration();

        $configuration->set('foo', 'bar');
        $configuration->set('bar', 'foo');

        $mappedConfiguration = $configuration->map(fn (string $value): string => sprintf('%s_value', $value));

        self::assertContains('bar_value', $mappedConfiguration);
        self::assertContains('foo_value', $mappedConfiguration);
    }
}
