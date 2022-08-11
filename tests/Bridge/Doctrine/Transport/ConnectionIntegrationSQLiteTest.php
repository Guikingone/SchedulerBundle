<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\DriverManager;
use SchedulerBundle\Bridge\Doctrine\Transport\Connection;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;

use function file_exists;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_sqlite
 */
final class ConnectionIntegrationSQLiteTest extends AbstractConnectionIntegrationTest
{
    private string $sqliteFile;

    protected function buildConnection(): Connection
    {
        $this->sqliteFile = sys_get_temp_dir().'/symfony.scheduler.sqlite';

        $this->dbalConnection = DriverManager::getConnection(params: [
            'url' => sprintf('sqlite:///%s', $this->sqliteFile),
        ]);

        return new Connection(new InMemoryConfiguration([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
            'execution_mode' => 'first_in_first_out',
        ], [
            'auto_setup' => 'bool',
            'table_name' => 'string',
        ]), $this->dbalConnection, $this->buildSerializer(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->sqliteFile)) {
            unlink($this->sqliteFile);
        }
    }
}
