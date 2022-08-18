<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\FiberConfiguration;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * @requires PHP 8.1
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberConfigurationTest extends TestCase
{
    public function testConfigurationCanBeCreated(): void
    {
        $configuration = new FiberConfiguration(new InMemoryConfiguration());
        $configuration->set('foo', 'bar');

        self::assertSame(2, $configuration->count());
        self::assertArrayHasKey('execution_mode', $configuration->toArray());
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testConfigurationCanBeInitialized(): void
    {
        $configuration = new FiberConfiguration(new InMemoryConfiguration());
        $configuration->init([
            'execution_mode' => 'batch',
        ]);

        self::assertSame('batch', $configuration->get('execution_mode'));
    }

    public function testConfigurationCannotBeInitializedWithExtraOptionsAndInvalidTypes(): void
    {
        $configuration = new FiberConfiguration(new InMemoryConfiguration());

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
        $configuration = new FiberConfiguration(new InMemoryConfiguration());
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
        $configuration = new FiberConfiguration(new InMemoryConfiguration());
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));

        $configuration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->update('foo', 'foo_new');

        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('foo_new', $configuration->get('foo'));
    }

    public function testConfigurationCanBeRemoved(): void
    {
        $configuration = new FiberConfiguration(new InMemoryConfiguration());

        $configuration->set('foo', 'bar');

        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('bar', $configuration->get('foo'));

        $configuration->remove('foo');

        self::assertArrayNotHasKey('foo', $configuration->toArray());
        self::assertNull($configuration->get('foo'));
    }

    public function testConfigurationCanMapValues(): void
    {
        $configuration = new FiberConfiguration(new InMemoryConfiguration());

        $configuration->set('foo', 'bar');
        $configuration->set('bar', 'foo');

        $mappedConfiguration = $configuration->map(static fn (string $value): string => sprintf('%s_value', $value));

        self::assertContains('bar_value', $mappedConfiguration);
        self::assertContains('foo_value', $mappedConfiguration);
    }
}
