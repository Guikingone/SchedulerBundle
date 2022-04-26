<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Connection;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractDoctrineConnection
{
    public function __construct(private DBALConnection $driverConnection)
    {
    }

    /**
     * Determine the table that should be added to the current @param Schema $schema.
     */
    abstract protected function addTableToSchema(Schema $schema): void;

    /**
     * @param array<int|string, mixed>                $parameters
     * @param array<int|string, int|string|Type|null> $types
     * @return mixed
     */
    abstract protected function executeQuery(string $sql, array $parameters = [], array $types = []);

    abstract public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void;

    /**
     * @throws Exception
     */
    protected function updateSchema(): void
    {
        $schemaManager = $this->driverConnection->createSchemaManager();
        $comparator = $schemaManager->createComparator();

        $schemaDiff = $comparator->compareSchemas($schemaManager->createSchema(), $this->getSchema($schemaManager));

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

    private function getSchema(AbstractSchemaManager $schemaManager): Schema
    {
        $schema = new Schema([], [], $schemaManager->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }
}
