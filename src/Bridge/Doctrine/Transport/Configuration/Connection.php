<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Closure;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use SchedulerBundle\Bridge\Doctrine\Connection\AbstractDoctrineConnection;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Configuration\ExternalConnectionInterface;
use Throwable;
use function array_map;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection extends AbstractDoctrineConnection implements ExternalConnectionInterface
{
    private bool $autoSetup;

    public function __construct(
        DbalConnection $connection,
        bool $autoSetup
    ) {
        $this->autoSetup = $autoSetup;

        parent::__construct($connection);
    }

    /**
     * {@inheritdoc}
     */
    public function init(array $options, array $extraOptions = []): void
    {
        // TODO: Implement init() method.
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        // TODO: Implement update() method.
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        // TODO: Implement get() method.
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        try {
            $this->driverConnection->transactional(function (DbalConnection $connection) use ($key): void {
                $queryBuilder = $this->createQueryBuilder('_symfony_scheduler_configuration', 'scc');
                $queryBuilder->delete('_symfony_scheduler_configuration')
                    ->where($queryBuilder->expr()->eq('key_name', ':key'))
                    ->setParameter('key', $key, ParameterType::STRING)
                ;

                $statement = $connection->executeQuery(
                    $queryBuilder->getSQL(),
                    $queryBuilder->getParameters(),
                    $queryBuilder->getParameterTypes()
                );

                if (1 !== $statement->rowCount()) {
                    throw new InvalidArgumentException('The given key does not exist');
                }
            });
        } catch (Throwable $exception) {
            throw new ConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        $values = $this->toArray();

        return array_map($func, $values);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        try {
            $this->driverConnection->transactional(function (DbalConnection $connection): void {
                $queryBuilder = $this->createQueryBuilder('_symfony_scheduler_configuration', 'scc')
                    ->delete('scc')
                ;

                $connection->executeQuery($queryBuilder->getSQL());
            });
        } catch (Throwable $exception) {
            throw new ConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        try {
            return $this->driverConnection->transactional(function (DbalConnection $connection): int {
                $queryBuilder = $this->createQueryBuilder('_symfony_scheduler_configuration', 'scc');

                $statement = $connection->executeQuery($queryBuilder->getSQL());
                $result = $statement->fetchAssociative();

                if (!$result) {
                    throw new RuntimeException('No result found');
                }

                return $result;
            });
        } catch (Throwable $exception) {
            throw new ConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        try {
            return $this->driverConnection->transactional(function (DbalConnection $connection): int {
                $queryBuilder = $this->createQueryBuilder('_symfony_scheduler_configuration', 'scc')
                    ->select('COUNT(scc.key_name) AS keys')
                ;

                $statement = $connection->executeQuery($queryBuilder->getSQL());
                $result = $statement->fetchAssociative();

                if (!$result) {
                    throw new RuntimeException('No result found');
                }

                return $result['keys'];
            });
        } catch (Throwable $exception) {
            throw new ConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable('_symfony_scheduler_configuration');
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true)
        ;
        $table->addColumn('key_name', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('key_value', Types::BLOB)
            ->setNotnull(true)
        ;

        $table->setPrimaryKey(['id']);
        $table->addIndex(['key_name'], '_symfony_scheduler_configuration_key');
    }

    /**
     * {@inheritdoc}
     */
    protected function executeQuery(string $sql, array $parameters = [], array $types = [])
    {
        try {
            return $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (Throwable $throwable) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $throwable;
            }

            if ($this->autoSetup) {
                $this->setup();
            }

            return $this->driverConnection->executeQuery($sql, $parameters, $types);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureSchema(Schema $schema, DBALConnection $dbalConnection): void
    {
        if ($dbalConnection !== $this->driverConnection) {
            return;
        }

        if ($schema->hasTable('_symfony_scheduler_configuration')) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    /**
     * @throws Exception
     */
    private function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        $schemaAssetsFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter();
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($schemaAssetsFilter);

        $this->autoSetup = false;
    }
}
