<?php

declare(strict_types=1);

namespace SchedulerBundle\Attribute;

use Attribute;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CommandTask
{
    private string $name;
    private string $expression;
    private bool $singleRun;
    private array $arguments;
    private array $options;
}
