<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_key_exists;
use function array_walk;
use function is_array;
use function is_string;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTask extends AbstractTask
{
    /**
     * @param array<string, mixed> $clientOptions
     */
    public function __construct(string $name, string $url, string $method = 'GET', array $clientOptions = [])
    {
        $this->validateClientOptions(clientOptions: $clientOptions);
        $this->defineOptions(options: [
            'url' => $url,
            'method' => $method,
            'client_options' => $clientOptions,
        ], additionalOptions: [
            'url' => 'string',
            'method' => 'string',
            'client_options' => ['array', 'string[]'],
        ]);

        parent::__construct(name: $name);
    }

    public function getUrl(): string
    {
        if (!is_string(value: $this->options['url'])) {
            throw new RuntimeException(message: 'The url is not defined');
        }

        return $this->options['url'];
    }

    public function setUrl(string $url): self
    {
        $this->options['url'] = $url;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->options['method'] ?? 'GET';
    }

    public function setMethod(string $method): self
    {
        $this->options['method'] = $method;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientOptions(): array
    {
        return is_array(value: $this->options['client_options']) ? $this->options['client_options'] : [];
    }

    /**
     * @param array<string, mixed> $clientOptions
     */
    public function setClientOptions(array $clientOptions): self
    {
        $this->options['client_options'] = $clientOptions;

        return $this;
    }

    /**
     * @param array<string,mixed> $clientOptions
     */
    private function validateClientOptions(array $clientOptions = []): void
    {
        if ([] === $clientOptions) {
            return;
        }

        array_walk(array: $clientOptions, callback: function ($_, $key): void {
            if (!array_key_exists(key: $key, array: HttpClientInterface::OPTIONS_DEFAULTS)) {
                throw new InvalidArgumentException(message: sprintf('The following option: "%s" is not supported', $key));
            }
        });
    }
}
