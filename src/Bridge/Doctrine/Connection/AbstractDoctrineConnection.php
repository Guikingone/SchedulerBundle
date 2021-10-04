<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Connection;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractDoctrineConnection
{
    protected ConfigurationInterface $configuration;
    protected DoctrineConnection $driverConnection;

    public function __construct(
        ConfigurationInterface $configuration,
        DoctrineConnection $driverConnection
    ) {
        $this->configuration = $configuration;
        $this->driverConnection = $driverConnection;
    }

    abstract protected function addTableToSchema(Schema $schema): void;

    public function configureSchema(Schema $schema, DbalConnection $connection): void
    {
        if ($connection !== $this->driverConnection) {
            return;
        }

        if ($schema->hasTable($this->configuration->get('table_name'))) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    public function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        $assetFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(null);
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($assetFilter);

        $this->configuration->set('auto_setup', false);
    }

    protected function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->driverConnection->createSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    protected function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->driverConnection->createQueryBuilder()
            ->select(sprintf('%s.*', $alias))
            ->from($this->configuration->get('table_name'), $alias)
        ;
    }

    protected function executeQuery(string $sql, array $parameters = [], array $types = [])
    {
        try {
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (Throwable $throwable) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $throwable;
            }

            if ($this->configuration->get('auto_setup')) {
                $this->setup();
            }

            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        }

        return $stmt;
    }

    private function updateSchema(): void
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($this->driverConnection->getSchemaManager()->createSchema(), $this->getSchema());

        foreach ($schemaDiff->toSaveSql($this->driverConnection->getDatabasePlatform()) as $sql) {
            $this->driverConnection->executeStatement($sql);
        }
    }
}
