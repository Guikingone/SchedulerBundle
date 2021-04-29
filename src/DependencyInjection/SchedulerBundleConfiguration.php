<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use function array_key_exists;
use function count;
use function in_array;

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
                    ->append($this->addTasksSection())
                    ->scalarNode('lock_store')
                        ->info('The store used by every worker to prevent overlapping, by default, a FlockStore is created')
                        ->defaultValue(null)
                    ->end()
                    ->scalarNode('rate_limiter')
                        ->info('The limiter used to control the execution and retry of tasks, MUST be a valid limiter identifier')
                        ->defaultValue(null)
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private function addTasksSection()
    {
        $treeBuilder = new TreeBuilder('tasks');

        return  $treeBuilder->getRootNode()
            ->useAttributeAsKey('name')
            ->normalizeKeys(false)
            ->arrayPrototype()
                ->validate()
                    ->always(function ($v) {
                        if (0=== count($v['arguments'])) {
                            unset($v['arguments']);
                        }

                        if (0=== count($v['tags'])) {
                            unset($v['tags']);
                        }

                        if (0=== count($v['options'])) {
                            unset($v['options']);
                        }

                        if (0=== count($v['tasks'])) {
                            unset($v['tasks']);
                        }

                        return $v;
                    })
                ->end()
                ->validate()
                    ->ifTrue(static fn ($v): bool => !in_array($v['type'], ['null', 'chained'], true)  && !isset($v['command']))
                    ->then(static function (array $v): void {
                        throw new InvalidConfigurationException(sprintf('You must specify the "command" if you define "%s" task type.', $v['type']));
                    })
                ->end()
                ->validate()
                    ->ifTrue(static fn ($v): bool => 'command' !== $v['type'] && isset($v['arguments']))
                    ->thenInvalid('The "arguments" option can only be defined for "command" task type.')
                ->end()
                ->validate()
                    ->ifTrue(static fn ($v): bool =>  'chained' === $v['type'] && !isset($v['tasks']))
                    ->thenInvalid('The "chained" type requires that you provide tasks.')
                ->end()
                ->children()
                    ->enumNode('type')
                        ->info('The Task type to build')
                        ->isRequired()
                        ->values(['shell', 'null', 'http', 'command', 'chained'])
                    ->end()
                    ->scalarNode('description')->end()
                    ->variableNode('command')->end()
                    ->arrayNode('arguments')
                        ->info('arguments to passed to "command" task type')
                        ->normalizeKeys(false)
                        ->variablePrototype()->end()
                    ->end()
                    ->integerNode('priority')
                         ->min(-1000)->max(1000)
                    ->end()
                    ->scalarNode('expression')->end()
                    ->booleanNode('single_run')->end()
                    ->booleanNode('output')->end()
                    ->booleanNode('output_to_store')->end()
                    ->arrayNode('options')
                        ->useAttributeAsKey('name')
                        ->normalizeKeys(false)
                        ->variablePrototype()->end()
                    ->end()
                    ->arrayNode('tags')
                        ->normalizeKeys(false)
                        ->variablePrototype()->end()
                    ->end()
                    ->scalarNode('timezone')->end()
                    ->scalarNode('tracked')->end()
                    ->scalarNode('state')->end()
                    ->integerNode('nice')->end()
                    ->integerNode('max_executions')->end()
                    ->floatNode('max_duration')->end()
                    ->integerNode('execution_delay')->end()
                    ->arrayNode('tasks')
                        ->info('Tasks list only available for "chained" type')
                        ->normalizeKeys(false)
                        ->prototype('variable')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
