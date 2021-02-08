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
    public function create(Dsn $dsn, SerializerInterface $serializer): ConfigurationInterface;

    public function support(string $dsn): bool;
}
