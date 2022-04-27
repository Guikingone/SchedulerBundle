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
    /**
     * {@inheritdoc}
     */
    public function init(array $options, array $extraOptions = []): void
    {
        $finalOptions = $this->defineOptions($options, $extraOptions);

        array_walk($finalOptions, function (mixed $option, string $key): void {
            $this->set($key, $option);
        });
    }

    /**
     * @param array<string, mixed> $options The default options required to make the configuration work.
     * @param array<string, mixed> $extraOptions A set of extra options that can be passed if required.
     *
     * @return array<string, mixed>
     */
    private function defineOptions(array $options = [], array $extraOptions = []): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'execution_mode' => 'first_in_first_out',
        ]);

        $resolver->setAllowedTypes('execution_mode', 'string');

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
