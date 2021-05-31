<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function array_key_exists;
use function array_walk;
use function count;
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
        $this->validateClientOptions($clientOptions);
        $this->defineOptions([
            'url' => $url,
            'method' => $method,
            'client_options' => $clientOptions,
        ], [
            'url' => 'string',
            'method' => 'string',
            'client_options' => ['array', 'string[]'],
        ]);

        parent::__construct($name);
    }

    public function getUrl(): string
    {
        return $this->options['url'];
    }

    public function setUrl(string $url): self
    {
        $this->options['url'] = $url;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->options['method'];
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
        return $this->options['client_options'];
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
        if (0 === count($clientOptions)) {
            return;
        }

        array_walk($clientOptions, function ($_, $key): void {
            if (!array_key_exists($key, HttpClientInterface::OPTIONS_DEFAULTS)) {
                throw new InvalidArgumentException(sprintf('The following option: "%s" is not supported', $key));
            }
        });
    }
}
