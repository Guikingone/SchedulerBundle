<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

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
            'filename_mask' => '%s/_symfony_scheduler_/configuration',
            'file_extension' => 'json',
        ], $options), [
            'path' => 'string',
            'filename_mask' => 'string',
            'file_extension' => 'string',
        ]);

        $this->filesystem = new Filesystem();
        $this->serializer = $serializer;
    }

    public function set(string $key, $value): void
    {
        // TODO: Implement set() method.
    }

    public function update(string $key, $newValue): void
    {
        // TODO: Implement update() method.
    }

    public function get(string $key): void
    {
    }

    public function remove(string $key): void
    {
        // TODO: Implement remove() method.
    }

    public function toArray(): array
    {
        // TODO: Implement getOptions() method.
    }
}
