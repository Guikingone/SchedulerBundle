<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Configuration\Connection;
use function file_exists;
use function sprintf;
use function unlink;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_sqlite
 */
final class ConnectionIntegrationTest extends TestCase
{
    private Connection $connection;
    private DbalConnection $driverConnection;
    private string $sqliteFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->sqliteFile = getcwd().'/tests/Bridge/Doctrine/Transport/Configuration/.assets/_symfony_scheduler_connection.sqlite';
        $this->driverConnection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', $this->sqliteFile),
        ]);
        $this->connection = new Connection($this->driverConnection, true);
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
        $list = $this->connection->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->connection->count());
    }

    public function testConfigurationCanSetANewKey(): void
    {
        $list = $this->connection->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->connection->count());

        $this->connection->set('foo', 'bar');

        self::assertCount(1, $this->connection->toArray());
        self::assertSame(1, $this->connection->count());
        self::assertSame('bar', $this->connection->get('foo'));
    }

    public function testConnectionCanUpdateAKey(): void
    {
        $list = $this->connection->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->connection->count());

        $this->connection->set('foo', 'bar');

        self::assertCount(1, $this->connection->toArray());
        self::assertSame(1, $this->connection->count());
        self::assertSame('bar', $this->connection->get('foo'));

        $this->connection->update('foo', 'random');

        self::assertCount(1, $this->connection->toArray());
        self::assertSame(1, $this->connection->count());
        self::assertSame('random', $this->connection->get('foo'));
    }

    public function testConnectionCanRemoveAKey(): void
    {
        $list = $this->connection->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->connection->count());

        $this->connection->set('foo', 'bar');

        self::assertCount(1, $this->connection->toArray());
        self::assertSame(1, $this->connection->count());
        self::assertSame('bar', $this->connection->get('foo'));

        $this->connection->remove('foo');

        self::assertCount(0, $this->connection->toArray());
        self::assertSame(0, $this->connection->count());
    }

    public function testConnectionCanClear(): void
    {
        $list = $this->connection->toArray();

        self::assertCount(0, $list);
        self::assertSame(0, $this->connection->count());

        $this->connection->set('foo', 'bar');

        self::assertCount(1, $this->connection->toArray());
        self::assertSame(1, $this->connection->count());
        self::assertSame('bar', $this->connection->get('foo'));

        $this->connection->clear();

        self::assertCount(0, $this->connection->toArray());
        self::assertSame(0, $this->connection->count());
    }
}
