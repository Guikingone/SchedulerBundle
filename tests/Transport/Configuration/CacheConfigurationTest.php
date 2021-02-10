<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Transport\Configuration\CacheConfiguration;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheConfigurationTest extends TestCase
{
    public function testConfigurationCannotSetWithExistingKey(): void
    {
        $configuration = new CacheConfiguration(new ArrayAdapter());
        $configuration->set('foo', 'bar');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The key "foo" already exist, consider using SchedulerBundle\Transport\Configuration\CacheConfiguration::update()');
        self::expectExceptionCode(0);
        $configuration->set('foo', 'bar');
    }

    public function testConfigurationCanSet(): void
    {
        $configuration = new CacheConfiguration(new ArrayAdapter());
        $configuration->set('foo', 'bar');

        self::assertSame('bar', $configuration->get('foo'));
    }

    public function testConfigurationCannotUpdateUndefinedKey(): void
    {
        $configuration = new CacheConfiguration(new ArrayAdapter());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The configuration key "foo" does not exist');
        self::expectExceptionCode(0);
        $configuration->update('foo', 'bar');
    }

    public function testConfigurationCanUpdate(): void
    {
        $configuration = new CacheConfiguration(new ArrayAdapter());
        $configuration->set('foo', 'bar');

        $configuration->update('foo', 'foo');

        self::assertSame('foo', $configuration->get('foo'));
    }

    public function testConfigurationCannotReturnUndefinedKey(): void
    {
        $configuration = new CacheConfiguration(new ArrayAdapter());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The configuration key "foo" does not exist');
        self::expectExceptionCode(0);
        $configuration->get('foo');
    }

    public function testConfigurationCanRemove(): void
    {
        $configuration = new CacheConfiguration(new ArrayAdapter());
        $configuration->set('foo', 'bar');

        self::assertSame('bar', $configuration->get('foo'));
        $configuration->remove('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The configuration key "foo" does not exist');
        self::expectExceptionCode(0);
        $configuration->get('foo');
    }

    public function testConfigurationCanReturnAll(): void
    {
        $configuration = new CacheConfiguration(new ArrayAdapter());
        $configuration->set('foo', 'bar');

        self::assertNotEmpty($configuration->getOptions());
    }
}
