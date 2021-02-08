<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_walk;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractConfiguration implements ConfigurationInterface
{
    public function init(array $options, array $extraOptions = []): void
    {
        $finalOptions = $this->defineOptions($options, $extraOptions);

        array_walk($finalOptions, fn ($option, string $key) => $this->set($key, $option));
    }

    private function defineOptions(array $options = [], array $extraOptions = []): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'execution_mode' => 'first_in_first_out',
        ]);

        $resolver->setAllowedTypes('execution_mode', 'string');
        $resolver->setInfo('execution_mode', 'The execution mode used to sort the tasks');

        if ([] === $extraOptions) {
            return $resolver->resolve($options);
        }

        foreach ($extraOptions as $extraOption => $allowedTypes) {
            $resolver->setDefined($extraOption);
            $resolver->setAllowedTypes($extraOption, $allowedTypes);
        }

        return $resolver->resolve($options);
    }
}
