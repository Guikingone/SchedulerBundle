<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractDoctrineConnection
{
    private DbalConnection $driverConnection;

    public function __construct(DBALConnection $driverConnection)
    {
        $this->driverConnection = $driverConnection;
    }

    abstract protected function addTableToSchema(Schema $schema): void;

    abstract protected function executeQuery(string $sql, array $parameters = [], array $types = []);

    /**
     * @throws Exception
     */
    protected function updateSchema(): void
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($this->driverConnection->getSchemaManager()->createSchema(), $this->getSchema());

        foreach ($schemaDiff->toSaveSql($this->driverConnection->getDatabasePlatform()) as $sql) {
            $this->driverConnection->executeStatement($sql);
        }
    }

    protected function createQueryBuilder(string $table, string $alias): QueryBuilder
    {
        return $this->driverConnection->createQueryBuilder()
            ->select(sprintf('%s.*', $alias))
            ->from($table, $alias)
        ;
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->driverConnection->getSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }
}
