<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use function array_merge;
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
    /**
     * @var string
     */
    private $scheme;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string|null
     */
    private $user;

    /**
     * @var string|null
     */
    private $password;

    /**
     * @var int|null
     */
    private $port;

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * @var string|null
     */
    private $path;

    public function __construct(string $scheme, string $host, ?string $path = null, ?string $user = null, ?string $password = null, ?int $port = null, array $options = [])
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->user = $user;
        $this->path = $path;
        $this->password = $password;
        $this->port = $port;
        $this->options = $options;
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

        return new self($parsedDsn['scheme'], $parsedDsn['host'], $path, $user, $password, $port, array_merge($query, $embeddedDsn));
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

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @return mixed[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return string[]
     */
    private static function handleEmbeddedDsn(string $dsn): array
    {
        preg_match('#\(([^()]|(?R))*\)#', $dsn, $matches);

        if (empty($matches)) {
            return [];
        }

        return [strtr($matches[0], ['(' => '', ')' => ''])];
    }
}
