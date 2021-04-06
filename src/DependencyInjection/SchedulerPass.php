<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPass implements CompilerPassInterface
{
    private string $schedulerExtraTag;

    public function __construct(string $schedulerExtraTag = 'scheduler.extra')
    {
        $this->schedulerExtraTag = $schedulerExtraTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $containerBuilder): void
    {
        $this->registerExtra($containerBuilder);
    }

    private function registerExtra(ContainerBuilder $containerBuilder): void
    {
        foreach ($containerBuilder->findTaggedServiceIds($this->schedulerExtraTag) as $service => $args) {
            if (!$containerBuilder->hasDefinition($args[0]['require'])) {
                $containerBuilder->removeDefinition($service);

                continue;
            }

            $containerBuilder->getDefinition($service)->addTag($args[0]['tag']);
        }
    }
}
