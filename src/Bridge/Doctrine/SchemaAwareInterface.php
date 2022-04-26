<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchemaAwareInterface
{
    /**
     * Allo the transport and/or configuration to interact with the @param Connection $dbalConnection.
     *
     * The schema can be defined using @param Schema $schema.
     */
    public function configureSchema(Schema $schema, Connection $dbalConnection): void;
}
