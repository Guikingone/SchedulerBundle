<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer\Assets;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FooMessage
{
    public function __construct(private int $id = 1)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
