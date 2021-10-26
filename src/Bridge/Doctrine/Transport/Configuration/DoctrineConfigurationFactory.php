<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\Persistence\ConnectionRegistry;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Transport\Configuration\ConfigurationFactoryInterface;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineConfigurationFactory implements ConfigurationFactoryInterface
{
    private ConnectionRegistry $registry;

    public function __construct(ConnectionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): DoctrineConfiguration
    {
        try {
            $doctrineConnection = $this->registry->getConnection($dsn->getHost());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new TransportException(sprintf('Could not find Doctrine connection from Scheduler configuration DSN "doctrine://%s".', $dsn->getHost()), 0, $invalidArgumentException);
        }

        if (!$doctrineConnection instanceof DoctrineConnection) {
            throw new RuntimeException('The connection is not a valid one');
        }

        return new DoctrineConfiguration($doctrineConnection, $dsn->getOptionAsBool('auto_setup', false));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://doctrine') || 0 === strpos($dsn, 'configuration://dbal');
    }
}
