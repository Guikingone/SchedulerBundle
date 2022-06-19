<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\DriverManager;
use SchedulerBundle\Bridge\Doctrine\Transport\Connection;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Dsn;
use Tests\SchedulerBundle\Bridge\Doctrine\Transport\AbstractConnectionIntegrationTest;
use function getenv;
use function is_bool;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_pgsql
 */
final class ConnectionIntegrationPostgresqlTest extends AbstractConnectionIntegrationTest
{
    protected function buildConnection(): Connection
    {
        $postgresDsn = getenv('SCHEDULER_POSTGRES_DSN');
        if (is_bool($postgresDsn)) {
            self::markTestSkipped(message: 'The "SCHEDULER_POSTGRES_DSN" environment variable is required.');
        }

        $postgresDsn = Dsn::fromString(dsn: $postgresDsn);

        $this->dbalConnection = DriverManager::getConnection([
            'charset' => 'utf8',
            'dbname' => '_symfony_scheduler_tasks',
            'driver' => 'pdo_pgsql',
            'host' => $postgresDsn->getHost(),
            'user' => $postgresDsn->getUser() ?? 'toor',
            'password' => $postgresDsn->getPassword() ?? 'root',
        ]);

        return new Connection(configuration: new InMemoryConfiguration(options: [
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
            'execution_mode' => 'first_in_first_out',
        ], extraOptions: [
            'auto_setup' => 'bool',
            'table_name' => 'string',
        ]), dbalConnection: $this->dbalConnection, serializer: $this->buildSerializer(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ]));
    }
}
