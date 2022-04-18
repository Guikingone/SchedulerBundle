<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractTransport implements TransportInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [
        'execution_mode' => 'first_in_first_out',
    ];

    public function __construct(protected ConfigurationInterface $configuration)
    {
    }

    protected function defineOptions(array $options = [], array $additionalOptions = []): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults([
            'execution_mode' => 'first_in_first_out',
        ]);

        $optionsResolver->setAllowedTypes('execution_mode', ['string', 'null']);

        if ([] === $additionalOptions) {
            $this->options = $optionsResolver->resolve($options);
        }

        foreach ($additionalOptions as $additionalOption => $allowedTypes) {
            $optionsResolver->setDefined($additionalOption);
            $optionsResolver->setAllowedTypes($additionalOption, $allowedTypes);
        }

        $this->options = $optionsResolver->resolve($options);
    }

    public function getExecutionMode(): string
    {
        return $this->configuration->get('execution_mode');
    }

    public function setExecutionMode(string $executionMode): self
    {
        $this->configuration->set('execution_mode', $executionMode);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }
}
