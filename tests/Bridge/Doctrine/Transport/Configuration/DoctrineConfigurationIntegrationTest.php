<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Configuration\DoctrineConfiguration;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use function file_exists;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineConfigurationIntegrationTest extends TestCase
{
    private ConfigurationInterface $configuration;
    private DbalConnection $driverConnection;
    private string $sqliteFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->sqliteFile = sys_get_temp_dir().'/_symfony_scheduler_configuration_integration.sqlite';
        $this->driverConnection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', $this->sqliteFile),
        ]);
        $this->configuration = new DoctrineConfiguration($this->driverConnection, true);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->driverConnection->close();
        if (file_exists($this->sqliteFile)) {
            unlink($this->sqliteFile);
        }
    }

    public function testConfigurationCanReturnArray(): void
    {
        $list = $this->configuration->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->configuration->count());
    }

    public function testConfigurationCanSetANewKey(): void
    {
        $list = $this->configuration->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->configuration->count());

        $this->configuration->set('foo', 'bar');

        self::assertCount(1, $this->configuration->toArray());
        self::assertSame(1, $this->configuration->count());
        self::assertSame('bar', $this->configuration->get('foo'));
    }

    public function testConnectionCanUpdateAKey(): void
    {
        $list = $this->configuration->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->configuration->count());

        $this->configuration->set('foo', 'bar');

        self::assertCount(1, $this->configuration->toArray());
        self::assertSame(1, $this->configuration->count());
        self::assertSame('bar', $this->configuration->get('foo'));

        $this->configuration->update('foo', 'random');

        self::assertCount(1, $this->configuration->toArray());
        self::assertSame(1, $this->configuration->count());
        self::assertSame('random', $this->configuration->get('foo'));
    }

    public function testConnectionCanRemoveAKey(): void
    {
        $list = $this->configuration->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->configuration->count());

        $this->configuration->set('foo', 'bar');

        self::assertCount(1, $this->configuration->toArray());
        self::assertSame(1, $this->configuration->count());
        self::assertSame('bar', $this->configuration->get('foo'));

        $this->configuration->remove('foo');

        self::assertCount(0, $this->configuration->toArray());
        self::assertSame(0, $this->configuration->count());
    }

    public function testConnectionCanClear(): void
    {
        $list = $this->configuration->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->configuration->count());

        $this->configuration->set('foo', 'bar');

        self::assertCount(1, $this->configuration->toArray());
        self::assertSame(1, $this->configuration->count());
        self::assertSame('bar', $this->configuration->get('foo'));

        $this->configuration->clear();

        self::assertCount(0, $this->configuration->toArray());
        self::assertSame(0, $this->configuration->count());
    }
}
