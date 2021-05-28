<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
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

        $this->addTasksSection($rootNode);

        return $treeBuilder;
    }

    /**
     * add the tasks section to configuration tree
     */
    private function addTasksSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('tasks')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->validate()
                        ->always(function ($v) {
                            if (0=== count($v['arguments'])) {
                                unset($v['arguments']);
                            }

                            if (0=== count($v['environment_variables'])) {
                                unset($v['environment_variables']);
                            }

                            if (0=== count($v['client_options'])) {
                                unset($v['client_options']);
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
                        ->ifTrue(static fn ($v): bool => in_array($v['type'], ['shell', 'command'], true)  && !isset($v['command']))
                        ->then(static function (array $v): void {
                            throw new InvalidConfigurationException(sprintf('You must specify the "command" if you define "%s" task type.', $v['type']));
                        })
                    ->end()
                    ->validate()
                        ->ifTrue(static fn ($v): bool => 'http' === $v['type'] && !isset($v['url']))
                        ->thenInvalid('You must specify the "url" if you define "http" task type.')
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
                        ->scalarNode('expression')->defaultValue('* * * * *')->end()
                        ->integerNode('priority')
                            ->min(-1000)->max(1000)
                        ->end()
                        ->booleanNode('single_run')->end()
                        ->booleanNode('output')->end()
                        ->booleanNode('output_to_store')->end()
                        ->integerNode('nice')->end()
                        ->integerNode('max_executions')->end()
                        ->integerNode('execution_delay')->end()
                        ->scalarNode('timezone')->end()
                        ->scalarNode('tracked')->end()
                        ->scalarNode('state')->end()
                        ->floatNode('max_duration')->end()
                        ->arrayNode('tags')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->variableNode('command')->info('The command to run. (shell|command type) ')->end()
                         ->scalarNode('cwd')->info('The working directory to use. (shell type)')->end()
                         ->arrayNode('environment_variables')
                            ->info('environment_variables to passed. (shell type)')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->floatNode('timeout')->info('The timeout in seconds to disable. (shell type)')->end()
                        ->arrayNode('arguments')
                            ->info('arguments to passed. (command type)')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->arrayNode('options')
                            ->info('options to passed. (command type)')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->scalarNode('url')->info('url (http type)')->end()
                        ->scalarNode('method')->info('HTTP Method. (http type)')->end()
                        ->arrayNode('client_options')
                            ->info('HTTP client options. (http type)')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->arrayNode('tasks')
                            ->info('Chained tasks. (chained type)')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
