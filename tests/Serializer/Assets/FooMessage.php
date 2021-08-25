<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer\Assets;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FooMessage
{
    private int $id;

    public function __construct(int $id = 1)
    {
        $this->id = $id;
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
