<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use function array_merge;
use function count;
use function parse_str;
use function parse_url;
use function preg_match;
use function sprintf;
use function strtr;
use function urldecode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Dsn
{
    private string $scheme;
    private string $host;
    private ?string $user;
    private ?string $password;
    private ?int $port;

    /**
     * @var array
     */
    private array $options;
    private ?string $path;
    private ?string $root;

    public function __construct(string $scheme, string $host, ?string $path = null, ?string $user = null, ?string $password = null, ?int $port = null, array $options = [], ?string $root = null)
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->user = $user;
        $this->path = $path;
        $this->password = $password;
        $this->port = $port;
        $this->options = $options;
        $this->root = $root;
    }

    public static function fromString(string $dsn): self
    {
        if (false === $parsedDsn = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The "%s" scheduler DSN is invalid.', $dsn));
        }

        if (!isset($parsedDsn['scheme'])) {
            throw new InvalidArgumentException(sprintf('The "%s" scheduler DSN must contain a scheme.', $dsn));
        }

        if (!isset($parsedDsn['host'])) {
            throw new InvalidArgumentException(sprintf('The "%s" scheduler DSN must contain a host (use "default" by default).', $dsn));
        }

        $user = isset($parsedDsn['user']) ? urldecode($parsedDsn['user']) : null;
        $password = isset($parsedDsn['pass']) ? urldecode($parsedDsn['pass']) : null;
        $port = $parsedDsn['port'] ?? null;
        $path = $parsedDsn['path'] ?? null;

        parse_str($parsedDsn['query'] ?? '', $query);

        $embeddedDsn = self::handleEmbeddedDsn($dsn);

        $self = new self($parsedDsn['scheme'], $parsedDsn['host'], $path, $user, $password, $port, array_merge($query, $embeddedDsn));
        $self->root = $dsn;

        return $self;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPort(int $default = null): ?int
    {
        return $this->port ?? $default;
    }

    /**
     * @param array|bool|int|null|string $default
     */
    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function getOptionAsBool(string $key, ?bool $default = null): bool
    {
        if ('false' === $this->getOption($key, $default)) {
            return false;
        }

        return (bool) $this->getOption($key, $default);
    }

    public function getOptionAsInt(string $key, int $default): int
    {
        return (int) $this->getOption($key, $default);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getRoot(): ?string
    {
        return $this->root;
    }

    /**
     * @return string[]
     */
    private static function handleEmbeddedDsn(string $dsn): array
    {
        preg_match('#\(([^()]|(?R))*\)#', $dsn, $matches);

        if (0 === count($matches)) {
            return [];
        }

        return [strtr($matches[0], ['(' => '', ')' => ''])];
    }
}
