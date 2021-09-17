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
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());
        $cacheConfiguration->set('foo', 'bar');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The key "foo" already exist, consider using SchedulerBundle\Transport\Configuration\CacheConfiguration::update()');
        self::expectExceptionCode(0);
        $cacheConfiguration->set('foo', 'bar');
    }

    public function testConfigurationCanSet(): void
    {
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());
        $cacheConfiguration->set('foo', 'bar');

        self::assertSame('bar', $cacheConfiguration->get('foo'));
    }

    public function testConfigurationCannotUpdateUndefinedKey(): void
    {
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The configuration key "foo" does not exist');
        self::expectExceptionCode(0);
        $cacheConfiguration->update('foo', 'bar');
    }

    public function testConfigurationCanUpdate(): void
    {
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());
        $cacheConfiguration->set('foo', 'bar');

        $cacheConfiguration->update('foo', 'foo');

        self::assertSame('foo', $cacheConfiguration->get('foo'));
    }

    public function testConfigurationCannotReturnUndefinedKey(): void
    {
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The configuration key "foo" does not exist');
        self::expectExceptionCode(0);
        $cacheConfiguration->get('foo');
    }

    public function testConfigurationCanRemove(): void
    {
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());
        $cacheConfiguration->set('foo', 'bar');

        self::assertSame('bar', $cacheConfiguration->get('foo'));
        $cacheConfiguration->remove('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The configuration key "foo" does not exist');
        self::expectExceptionCode(0);
        $cacheConfiguration->get('foo');
    }

    public function testConfigurationCanReturnAll(): void
    {
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());
        $cacheConfiguration->set('foo', 'bar');

        self::assertCount(2, $cacheConfiguration->toArray());
        self::assertContains('execution_mode', $cacheConfiguration->toArray());
        self::assertContains('foo', $cacheConfiguration->toArray());
    }

    public function testConfigurationCanReturnCount(): void
    {
        $cacheConfiguration = new CacheConfiguration(new ArrayAdapter());
        $cacheConfiguration->set('foo', 'bar');

        self::assertSame(2, $cacheConfiguration->count());
        self::assertContains('execution_mode', $cacheConfiguration->toArray());
        self::assertContains('foo', $cacheConfiguration->toArray());
    }
}
