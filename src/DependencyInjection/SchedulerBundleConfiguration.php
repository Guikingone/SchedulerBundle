<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function array_key_exists;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundleConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('scheduler_bundle');

        $treeBuilder
            ->getRootNode()
                ->beforeNormalization()
                    ->always(function (array $configuration): array {
                        if ((array_key_exists('tasks', $configuration)) && 0 !== count($configuration['tasks']) && !array_key_exists('transport', $configuration)) {
                            throw new InvalidConfigurationException('The transport must be configured to schedule tasks');
                        }

                        return $configuration;
                    })
                ->end()
                ->children()
                    ->scalarNode('path')
                        ->info('The path used to trigger tasks using http request, default to "/_tasks"')
                        ->defaultValue('/_tasks')
                    ->end()
                    ->scalarNode('timezone')
                        ->info('The timezone used by the scheduler, if not defined, the default value will be "UTC"')
                        ->defaultValue('UTC')
                    ->end()
                    ->arrayNode('transport')
                        ->children()
                            ->scalarNode('dsn')
                                ->info('The transport DSN used by the scheduler')
                            ->end()
                            ->arrayNode('options')
                            ->info('Configure the transport, every options handling is configured in each transport')
                            ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('execution_mode')
                                        ->info('The policy used to sort the tasks scheduled')
                                        ->defaultValue('first_in_first_out')
                                    ->end()
                                    ->scalarNode('path')
                                        ->info('The path used by the FilesystemTransport to store tasks')
                                        ->defaultValue('%kernel.project_dir%/var/tasks')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('tasks')
                        ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->scalarNode('lock_store')
                            ->info('The store used by every worker to prevent overlapping, by default, a FlockStore is created')
                        ->defaultValue(null)
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
