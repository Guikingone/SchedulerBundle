<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Closure;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use SchedulerBundle\Bridge\Doctrine\Connection\AbstractDoctrineConnection;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineConfiguration extends AbstractDoctrineConnection implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        // TODO: Implement set() method.
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
        try {
            $queryBuilder = $this->createQueryBuilder('c')
                ->where('c.configuration_key = :configuration_key')
                ->setParameter(':configuration_key', $key, ParameterType::STRING)
            ;

            $statement = $this->executeQuery(
                $queryBuilder->getSQL().' '.$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            );

            $data = $statement->fetchAssociative();
            if (empty($data)) {
                throw new LogicException('The desired configuration key cannot be found.');
            }

            return $data['configuration_value'];
        } catch (Throwable $throwable) {
            throw new ConfigurationException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        // TODO: Implement walk() method.
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        // TODO: Implement map() method.
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        // TODO: Implement clear() method.
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        // TODO: Implement getOptions() method.
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
    }

    protected function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->configuration->get('table_name'));
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true)
        ;
        $table->addColumn('configuration_key', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('configuration_value', Types::BLOB)
            ->setNotnull(false)
        ;

        $table->setPrimaryKey(['id']);
        $table->addIndex(['configuration_key'], '_symfony_scheduler_configuration_key');
    }
}
