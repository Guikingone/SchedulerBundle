<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemConfiguration extends AbstractConfiguration
{
    private Filesystem $filesystem;
    private SerializerInterface $serializer;

    public function __construct(array $options, SerializerInterface $serializer)
    {
        $this->init(array_merge([
            'path' => $options['path'] ?? sys_get_temp_dir(),
            'filename_mask' => $options['filename_mask'],
            'file_extension' => $options['file_extension'],
        ], $options), [
            'path' => 'string',
            'filename_mask' => 'string',
            'file_extension' => 'string',
        ]);

        $this->filesystem = new Filesystem();
        $this->serializer = $serializer;

        $this->checkDefaultFilePresence($options['path'], $options['filename_mask'], $options['file_extension']);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        // TODO: Implement set() method.
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        // TODO: Implement update() method.
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        // TODO: Implement remove() method.
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        // TODO: Implement walk() method.
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        // TODO: Implement getOptions() method.
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        // TODO: Implement clear() method.
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
    }

    private function checkDefaultFilePresence(string $path, string $filename, string $extension): bool
    {
        if ($this->filesystem->exists(sprintf('%s/%s.%s', $path, $filename, $extension))) {
            return true;
        }

        $this->filesystem->touch(sprintf('%s/%s.%s', $path, $filename, $extension));

        return true;
    }
}
