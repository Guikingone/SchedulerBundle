<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Closure;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr;
use SchedulerBundle\Bridge\Doctrine\Connection\AbstractDoctrineConnection;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Transport\Configuration\ExternalConnectionInterface;
use Throwable;

use function array_map;
use function array_walk;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection extends AbstractDoctrineConnection implements ExternalConnectionInterface
{
    private const TABLE_NAME = '_scheduler_transport_configuration';

    public function __construct(
        private DbalConnection $connection,
        private bool $autoSetup
    ) {
        parent::__construct($connection);
    }

    /**
     * {@inheritdoc}
     */
    public function init(array $options, array $extraOptions = []): void
    {
        $qb = $this->createQueryBuilder(self::TABLE_NAME, 'stc');
        $existingKeysQuery = $qb->select('stc.configuration_key_name')
            ->where($qb->expr()->in('stc.configuration_key_name', ':keys'))
            ->setParameter('keys', array_keys($options), DbalConnection::PARAM_STR_ARRAY)
        ;

        $existingConfigurationKeys = $this->executeQuery(
            $existingKeysQuery->getSQL(),
            $existingKeysQuery->getParameters(),
            $existingKeysQuery->getParameterTypes()
        )->fetchAllAssociative();

        if ([] !== $existingConfigurationKeys) {
            $existingConfigurationKeys = array_values($existingConfigurationKeys[0]);
            $alreadyStoredKeys = array_uintersect($existingConfigurationKeys, array_keys($options), 'strcasecmp');
            $nonStoredKeys = array_diff(array_keys($options), $alreadyStoredKeys);

            array_walk($nonStoredKeys, function (string $key) use ($options): void {
                if (array_key_exists($key, $options)) {
                    $this->set($key, $options[$key]);
                }
            });

            return;
        }

        array_walk($options, function (mixed $option, string $key): void {
            $this->set($key, $option);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): void
    {
        $qb = $this->createQueryBuilder(self::TABLE_NAME, 'stc');
        $existingConfigurationKeyQuery = $qb->select((new Expr())->countDistinct('stc.id'))
            ->where($qb->expr()->eq('stc.configuration_key_name', ':name'))
            ->setParameter('name', $key, ParameterType::STRING)
        ;

        $existingConfigurationKey = $this->executeQuery(
            $existingConfigurationKeyQuery->getSQL(),
            $existingConfigurationKeyQuery->getParameters(),
            $existingConfigurationKeyQuery->getParameterTypes()
        )->fetchOne();

        if (0 !== (int) $existingConfigurationKey) {
            return;
        }

        try {
            $this->connection->transactional(function () use ($key, $value): void {
                $query = $this->createQueryBuilder(self::TABLE_NAME, 'stc')
                    ->insert(self::TABLE_NAME)
                    ->values([
                        'configuration_key_name' => ':key',
                        'configuration_key_value' => ':value',
                    ])
                    ->setParameter('key', $key, ParameterType::STRING)
                    ->setParameter('value', $value)
                ;

                $statement = $this->executeQuery(
                    $query->getSQL(),
                    $query->getParameters(),
                    $query->getParameterTypes()
                );

                if (false !== $statement->fetchOne()) {
                    throw new Exception('The given data is invalid.');
                }
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, mixed $newValue): void
    {
        try {
            $this->connection->transactional(function () use ($key, $newValue): void {
                $queryBuilder = $this->createQueryBuilder(self::TABLE_NAME, 'stc');
                $queryBuilder->update(self::TABLE_NAME)
                    ->set('configuration_key_value', ':value')
                    ->where($queryBuilder->expr()->eq('configuration_key_name', ':name'))
                    ->setParameter('name', $key, ParameterType::STRING)
                    ->setParameter('value', $newValue)
                ;

                $this->executeQuery(
                    $queryBuilder->getSQL(),
                    $queryBuilder->getParameters(),
                    $queryBuilder->getParameterTypes()
                );
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $qb = $this->createQueryBuilder(self::TABLE_NAME, 'stc');
        $existingConfigurationKey = $qb->select((new Expr())->countDistinct('stc.id'))
            ->where($qb->expr()->eq('stc.configuration_key_name', ':name'))
            ->setParameter('name', $key, ParameterType::STRING)
        ;

        $statement = $this->executeQuery(
            $existingConfigurationKey->getSQL(),
            $existingConfigurationKey->getParameters(),
            $existingConfigurationKey->getParameterTypes()
        )->fetchOne();

        if (0 === (int) $statement) {
            throw new TransportException(sprintf('The key "%s" cannot be found', $key));
        }

        try {
            return $this->connection->transactional(function () use ($key): mixed {
                $queryBuilder = $this->createQueryBuilder(self::TABLE_NAME, 'stc');
                $queryBuilder->where($queryBuilder->expr()->eq('stc.configuration_key_name', ':name'))
                    ->setParameter('name', $key, ParameterType::STRING)
                ;

                $statement = $this->executeQuery(
                    $queryBuilder->getSQL(),
                    $queryBuilder->getParameters(),
                    $queryBuilder->getParameterTypes()
                );

                $keyRow = $statement->fetchAssociative();
                if (false === $keyRow) {
                    throw new LogicException('The desired configuration key cannot be found.');
                }

                return $keyRow['configuration_key_value'];
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        try {
            $this->connection->transactional(function () use ($key): void {
                $queryBuilder = $this->createQueryBuilder(self::TABLE_NAME, 'scc');
                $queryBuilder->delete(self::TABLE_NAME)
                    ->where($queryBuilder->expr()->eq('configuration_key_name', ':key'))
                    ->setParameter('key', $key, ParameterType::STRING)
                ;

                $statement = $this->executeQuery(
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
        $values = $this->toArray();

        array_walk($values, $func);
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
            $this->connection->transactional(function (): void {
                $queryBuilder = $this->createQueryBuilder(self::TABLE_NAME, 'stc')
                    ->delete(self::TABLE_NAME)
                ;

                $this->executeQuery($queryBuilder->getSQL());
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
        $existingKeysCount = $this->createQueryBuilder(self::TABLE_NAME, 'stc')
            ->select((new Expr())->countDistinct('stc.id'))
        ;

        $statement = $this->executeQuery(
            $existingKeysCount->getSQL(),
            $existingKeysCount->getParameters(),
            $existingKeysCount->getParameterTypes()
        )->fetchOne();

        if (0 === (int) $statement) {
            return [];
        }

        try {
            return $this->connection->transactional(function (): array {
                $queryBuilder = $this->createQueryBuilder(self::TABLE_NAME, 'stc');

                $statement = $this->executeQuery($queryBuilder->getSQL());

                $keys = $statement->fetchAllAssociative();
                if (!$keys) {
                    throw new RuntimeException('No result found');
                }

                return $keys;
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
            return $this->connection->transactional(function (): int {
                $queryBuilder = $this->createQueryBuilder(self::TABLE_NAME, 'stc')
                    ->select('COUNT(stc.configuration_key_name) AS keys')
                ;

                $statement = $this->executeQuery($queryBuilder->getSQL());
                $result = $statement->fetchAssociative();

                if (!$result) {
                    throw new RuntimeException('No result found');
                }

                return (int) $result['keys'];
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
        $table = $schema->createTable(self::TABLE_NAME);
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true)
        ;
        $table->addColumn('configuration_key_name', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('configuration_key_value', Types::BLOB)
            ->setNotnull(true)
        ;

        $table->setPrimaryKey(['id']);
        $table->addIndex(['configuration_key_name'], '_symfony_scheduler_configuration_key');
    }

    /**
     * {@inheritdoc}
     */
    protected function executeQuery(string $sql, array $parameters = [], array $types = [])
    {
        try {
            return $this->connection->executeQuery($sql, $parameters, $types);
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                throw $throwable;
            }

            if ($this->autoSetup) {
                $this->setup();
            }

            return $this->connection->executeQuery($sql, $parameters, $types);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void
    {
        if ($dbalConnection !== $this->connection) {
            return;
        }

        if ($schema->hasTable(self::TABLE_NAME)) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    /**
     * @throws Exception
     */
    private function setup(): void
    {
        $configuration = $this->connection->getConfiguration();
        $schemaAssetsFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter();
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($schemaAssetsFilter);

        $this->autoSetup = false;
    }
}
