<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException as InternalInvalidArgumentException;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Transport\Configuration\ConfigurationFactoryInterface;
use SchedulerBundle\Transport\Dsn;

use function sprintf;

use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineConfigurationFactory implements ConfigurationFactoryInterface
{
    public function __construct(private ConnectionRegistry $registry)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): DoctrineConfiguration
    {
        try {
            $doctrineConnection = $this->registry->getConnection($dsn->getHost());
        } catch (InternalInvalidArgumentException $invalidArgumentException) {
            throw new ConfigurationException(sprintf('Could not find Doctrine connection from Scheduler configuration DSN "doctrine://%s".', $dsn->getHost()), 0, $invalidArgumentException);
        }

        if (!$doctrineConnection instanceof DoctrineConnection) {
            throw new InvalidArgumentException('The connection is not a valid one');
        }

        return new DoctrineConfiguration($doctrineConnection, $dsn->getOptionAsBool('auto_setup', false));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return str_starts_with($dsn, 'configuration://doctrine') || str_starts_with($dsn, 'configuration://dbal');
    }
}
