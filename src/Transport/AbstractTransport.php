<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractTransport implements TransportInterface
{
    /**
     * @var mixed[]|string[]
     */
    protected $options;

    protected function defineOptions(array $options = [], array $additionalOptions = []): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'execution_mode' => 'first_in_first_out',
            'path' => null,
        ]);

        $resolver->setAllowedTypes('execution_mode', ['string', 'null']);
        $resolver->setAllowedTypes('path', ['string', 'null']);

        $resolver->setInfo('execution_mode', 'The execution mode used to sort the tasks');
        $resolver->setInfo('path', 'The path used to store the task (mainly used by FilesystemTransport)');

        if ($additionalOptions === []) {
            $this->options = $resolver->resolve($options);
        }

        foreach ($additionalOptions as $additionalOption => $allowedTypes) {
            $resolver->setDefined($additionalOption);
            $resolver->setAllowedTypes($additionalOption, $allowedTypes);
        }

        $this->options = $resolver->resolve($options);
    }

    public function getExecutionMode(): string
    {
        return $this->options['execution_mode'];
    }

    public function setExecutionMode(string $executionMode): self
    {
        $this->options['execution_mode'] = $executionMode;

        return $this;
    }

    /**
     * @return mixed[]|string[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
