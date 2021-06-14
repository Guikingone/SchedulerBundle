<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function array_map;
use function array_merge;
use function gettype;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryConfigurationFactory implements ConfigurationFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): InMemoryConfiguration
    {
        return new InMemoryConfiguration(array_merge([
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
        ], $dsn->getOptions()), array_map(fn ($value): string => gettype($value), $dsn->getOptions()));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://memory');
    }
}
