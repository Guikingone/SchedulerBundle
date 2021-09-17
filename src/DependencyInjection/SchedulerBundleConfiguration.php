<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function array_combine;
use function array_key_exists;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_replace;
use function array_values;
use function count;
use function sprintf;

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
                    ->always(static function (array $configuration): array {
                        if ((array_key_exists('tasks', $configuration)) && 0 !== count($configuration['tasks']) && !array_key_exists('transport', $configuration)) {
                            throw new InvalidConfigurationException('The transport must be configured to schedule tasks');
                        }

                        if (!array_key_exists('probe', $configuration)) {
                            return $configuration;
                        }

                        if (!array_key_exists('clients', $configuration['probe'])) {
                            return $configuration;
                        }

                        if (!array_key_exists('tasks', $configuration)) {
                            $configuration['tasks'] = [];
                        }

                        $configuration['tasks'] = array_merge($configuration['tasks'], array_map(static function (array $configuration): array {
                            $configuration['type'] = 'probe';
                            $configuration['expression'] = '* * * * *';

                            return $configuration;
                        }, array_combine(array_map(static fn ($key): string => sprintf('%s.probe', $key), array_keys($configuration['probe']['clients'])), $configuration['probe']['clients'])));

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
                    ->arrayNode('scheduler')
                        ->children()
                            ->enumNode('mode')
                                ->info('Define the scheduler mode (lazy or default)')
                                ->values(['default', 'lazy'])
                                ->defaultValue('default')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('configuration')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('mode')
                                ->info('Define the configuration mode (lazy or default)')
                                ->values(['default', 'lazy'])
                                ->defaultValue('default')
                            ->end()
                            ->scalarNode('dsn')
                                ->info('The configuration dsn used to store the transport configuration')
                                ->defaultValue('configuration://memory')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('probe')
                        ->children()
                            ->scalarNode('enabled')
                                ->info('Enable the probe')
                                ->defaultValue(false)
                            ->end()
                            ->scalarNode('path')
                                ->info('The path used by the probe to return the internal state')
                                ->defaultValue('/_probe')
                            ->end()
                            ->arrayNode('clients')
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('externalProbePath')
                                            ->info('Define the path where the probe state is available')
                                            ->defaultValue(null)
                                        ->end()
                                        ->scalarNode('errorOnFailedTasks')
                                            ->info('Define if the probe fails when the "failedTasks" node is higher than 0')
                                            ->defaultValue(false)
                                        ->end()
                                        ->scalarNode('delay')
                                            ->info('Define the delay before executing the client (in milliseconds)')
                                            ->defaultValue(0)
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('mercure')
                        ->beforeNormalization()
                            ->always(static function (array $configuration): array {
                                if (!array_key_exists('mercure', $configuration)) {
                                    return $configuration;
                                }

                                if (!$configuration['mercure']['enabled']) {
                                    return $configuration;
                                }

                                if (null === $configuration['mercure']['hub_url']) {
                                    throw new InvalidConfigurationException('The hub url must be set');
                                }

                                if (null === $configuration['mercure']['jwt_token']) {
                                    throw new InvalidConfigurationException('The jwt token must be set');
                                }

                                return $configuration;
                            })
                        ->end()
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('hub_url')
                                ->info('Define the Hub url')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('update_url')
                                ->info('Define the update url for every update dispatched')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('jwt_token')
                                ->info('Define the jwt token')
                                ->defaultNull()
                            ->end()
                        ->end()
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
                        ->beforeNormalization()
                            ->always(static function (array $taskConfiguration): array {
                                $chainedTasks = array_filter($taskConfiguration, static fn (array $configuration): bool => 'chained' === $configuration['type'] && 0 !== count($configuration['tasks']));

                                if (0 === count($chainedTasks)) {
                                    return $taskConfiguration;
                                }

                                $updatedChainedTasks = array_map(static function (array $chainedTaskConfiguration): array {
                                    foreach ($chainedTaskConfiguration['tasks'] as $chainedTask => &$configuration) {
                                        $configuration['name'] = $chainedTask;
                                    }

                                    $chainedTaskConfiguration['tasks'] = array_values($chainedTaskConfiguration['tasks']);

                                    return $chainedTaskConfiguration;
                                }, $chainedTasks);

                                return array_replace($taskConfiguration, $updatedChainedTasks);
                            })
                        ->end()
                        ->useAttributeAsKey('name')
                        ->variablePrototype()->end()
                    ->end()
                    ->scalarNode('lock_store')
                        ->info('The store used by every worker to prevent overlapping, by default, a FlockStore is created')
                        ->defaultValue(null)
                    ->end()
                    ->scalarNode('rate_limiter')
                        ->info('The limiter used to control the execution and retry of tasks, MUST be a valid limiter identifier')
                        ->defaultValue(null)
                    ->end()
                    ->arrayNode('pool')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('enabled')
                                ->info('Enable the pool support')
                                ->defaultValue(false)
                            ->end()
                            ->scalarNode('name')
                                ->info('The name of the current scheduler when used in a pool')
                                ->defaultValue(null)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
