<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use SchedulerBundle\Bridge\Doctrine\SchemaAwareInterface;
use SchedulerBundle\Transport\Configuration\AbstractExternalConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineConfiguration extends AbstractExternalConfiguration implements SchemaAwareInterface
{
    public function __construct(
        DbalConnection $connection,
        bool $autoSetup = false
    ) {
        parent::__construct(new Connection($connection, $autoSetup));
    }

    /**
     * {@inheritdoc}
     */
    public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void
    {
        if (!$this->connection instanceof Connection) {
            return;
        }

        $this->connection->configureSchema($schema, $dbalConnection);
    }
}
