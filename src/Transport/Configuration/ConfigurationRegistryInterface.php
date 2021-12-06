<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use Countable;
use IteratorAggregate;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConfigurationRegistryInterface extends Countable, IteratorAggregate
{
    public function usort(Closure $func): ConfigurationRegistryInterface;

    public function reset(): ConfigurationInterface;
}
