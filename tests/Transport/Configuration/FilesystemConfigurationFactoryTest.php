<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\FilesystemConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemConfigurationFactoryTest extends TestCase
{
    public function testConfigurationIsSupported(): void
    {
        $factory = new FilesystemConfigurationFactory();

        self::assertFalse($factory->support('configuration://array'));
        self::assertTrue($factory->support('configuration://filesystem'));
        self::assertTrue($factory->support('configuration://fs'));
    }

    public function testConfigurationIsReturned(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemConfigurationFactory();
        $configuration = $factory->create(Dsn::fromString('configuration://fs'), $serializer);

        self::assertCount(4, $configuration->toArray());
        self::assertArrayHasKey('execution_mode', $configuration->toArray());
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
        self::assertArrayHasKey('file_extension', $configuration->toArray());
        self::assertSame('json', $configuration->get('file_extension'));
        self::assertArrayHasKey('filename_mask', $configuration->toArray());
        self::assertSame('%s/_symfony_scheduler_/configuration', $configuration->get('file_extension'));
        self::assertArrayHasKey('path', $configuration->toArray());
        self::assertSame(sys_get_temp_dir(), $configuration->get('path'));
    }
}
