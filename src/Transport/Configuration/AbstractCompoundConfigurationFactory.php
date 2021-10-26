<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function array_map;
use function explode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractCompoundConfigurationFactory implements ConfigurationFactoryInterface
{
    /**
     * @param string                          $delimiter
     * @param Dsn                             $dsn
     * @param ConfigurationFactoryInterface[] $factories
     * @param SerializerInterface             $serializer
     *
     * @return ConfigurationInterface[]
     */
    protected function handleCompoundConfiguration(string $delimiter, Dsn $dsn, iterable $factories, SerializerInterface $serializer): array
    {
        if ('' === $delimiter) {
            throw new InvalidArgumentException('The delimiter cannot be empty, consider using a valid one like " && " or " || "');
        }

        $dsnList = $dsn->getOptions();
        if ([] === $dsnList) {
            throw new LogicException(sprintf('The %s configuration factory cannot create a configuration', static::class));
        }

        $finalDsnList = explode($delimiter, $dsnList[0]);
        if ($dsnList[0] === $finalDsnList[0]) {
            throw new InvalidArgumentException('The embedded dsn cannot be used to create a configuration');
        }

        return array_map(static function (string $configurationDsn) use ($factories, $serializer): ConfigurationInterface {
            foreach ($factories as $factory) {
                if (!$factory->support($configurationDsn)) {
                    continue;
                }

                return $factory->create(Dsn::fromString($configurationDsn), $serializer);
            }

            throw new InvalidArgumentException('The given dsn cannot be used to create a configuration');
        }, $finalDsnList);
    }
}
