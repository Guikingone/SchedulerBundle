<?php

declare(strict_types=1);

namespace SchedulerBundle\Attribute;

use Attribute;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsCommandTask
{
    public function __construct(
        public string $name,
        public string $expression,
        public ?bool $singleRun = false,
        public ?array $arguments = [],
        public ?array $options = []
    ) {}
}
