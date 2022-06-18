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
        $postgresDsn = getenv(name: 'SCHEDULER_POSTGRES_DSN');
        if (is_bool(value: $postgresDsn)) {
            self::markTestSkipped(message: 'The "SCHEDULER_POSTGRES_DSN" environment variable is required.');
        }

        $postgresDsn = getenv(name: 'SCHEDULER_POSTGRES_DSN');
        $postgresDsn = Dsn::fromString(dsn: $postgresDsn);

        $this->dbalConnection = DriverManager::getConnection(params: [
            'driver' => 'pdo_pgsql',
            'host' => $postgresDsn->getHost(),
            'port' => $postgresDsn->getPort(),
            'dbname' => '_symfony_scheduler_tasks',
            'user' => $postgresDsn->getUser(),
            'password' => $postgresDsn->getPassword(),
            'charset' => 'utf8',
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
}
