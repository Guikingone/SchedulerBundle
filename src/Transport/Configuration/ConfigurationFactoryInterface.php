<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConfigurationFactoryInterface
{
    /**
     * Create a new configuration (which can contains the options stored in @param Dsn $dsn) and receive the @param SerializerInterface $serializer.
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): ConfigurationInterface;

    /**
     * Determine if the factory can create a configuration using @param string $dsn.
     */
    public function support(string $dsn): bool;
}
