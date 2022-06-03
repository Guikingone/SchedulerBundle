<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_key_exists;
use function array_walk;
use function is_array;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTask extends AbstractTask
{
    private string $url;
    private string $method;

    /**
     * @param array<string, mixed> $clientOptions
     */
    public function __construct(string $name, string $url, string $method = 'GET', array $clientOptions = [])
    {
        $this->validateClientOptions(clientOptions: $clientOptions);
        $this->defineOptions(options: [
            'client_options' => $clientOptions,
        ], additionalOptions: [
            'client_options' => ['array', 'string[]'],
        ]);

        $this->url = $url;
        $this->method = $method;

        parent::__construct(name: $name);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

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
