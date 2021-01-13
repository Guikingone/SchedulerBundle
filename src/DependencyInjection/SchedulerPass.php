<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class SchedulerPass implements CompilerPassInterface
{
    /**
     * @var string
     */
    private $schedulerExtraTag;

    public function __construct(string $schedulerExtraTag = 'scheduler.extra')
    {
        $this->schedulerExtraTag = $schedulerExtraTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $this->registerExtra($container);
    }

    private function registerExtra(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->schedulerExtraTag) as $service => $args) {
            if (!$container->hasDefinition($args[0]['require'])) {
                $container->removeDefinition($service);

                continue;
            }

            $container->getDefinition($service)->addTag($args[0]['tag']);
        }
    }
}
