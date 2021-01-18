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
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('expression')
                                        ->info('The expression of the task')
                                        ->defaultValue('* * * * *')
                                    ->end()
                                    ->scalarNode('description')
                                        ->info('The description of the task')
                                    ->end()
                                    ->scalarNode('executionStartDate')
                                        ->info('The date where the task should start to be executed')
                                    ->end()
                                    ->scalarNode('executionEndDate')
                                        ->info('The date where the task should end to be executed')
                                    ->end()
                                    ->scalarNode('queued')
                                        ->info('If the task must be sent to a queue (requires "symfony/messenger")')
                                        ->defaultFalse()
                                    ->end()
                                    ->scalarNode('timezone')
                                        ->info('The timezone used by the task (this value override the one used by the Scheduler if set)')
                                        ->defaultValue('UTC')
                                    ->end()
                                    ->scalarNode('type')
                                        ->info('The type of task to create')
                                    ->end()
                                    ->variableNode('command')
                                        ->info('The command to execute')
                                    ->end()
                                    ->scalarNode('cwd')
                                        ->info('The working directory of a ShellTask')
                                    ->end()
                                    ->arrayNode('environment_variables')
                                        ->info('A list of environment variable')
                                        ->useAttributeAsKey('name')
                                        ->normalizeKeys(false)
                                            ->variablePrototype()->end()
                                    ->end()
                                    ->arrayNode('arguments')
                                        ->info('A list of arguments passed to CommandTask')
                                        ->useAttributeAsKey('name')
                                        ->normalizeKeys(false)
                                        ->variablePrototype()->end()
                                    ->end()
                                    ->arrayNode('options')
                                        ->info('A list of options passed to CommandTask')
                                        ->useAttributeAsKey('name')
                                        ->normalizeKeys(false)
                                        ->variablePrototype()->end()
                                    ->end()
                                    ->scalarNode('timeout')
                                        ->info('The timeout of the script executed via a ShellTask')
                                    ->end()
                                    ->scalarNode('url')
                                        ->info('The url to fetch using an HttpTask')
                                    ->end()
                                    ->scalarNode('method')
                                        ->info('The method used in an HttpTask')
                                    ->end()
                                    ->arrayNode('client_options')
                                        ->info('A list of options passed to the HttpClient')
                                        ->useAttributeAsKey('name')
                                        ->normalizeKeys(false)
                                        ->variablePrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
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
