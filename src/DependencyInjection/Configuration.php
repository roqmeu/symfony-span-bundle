<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection;

use Roqmeu\SpanBundle\DependencyInjection\Compiler\ProfilerMiddlewarePass;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder($this->name);
        $node = $tree->getRootNode()->addDefaultsIfNotSet()->children();

        $this->nodeDynamicEnabled($node, 'enabled', false);

        $tracingNode = $node->arrayNode('tracing')->addDefaultsIfNotSet()->children();
        $this->nodeDynamicEnabled($tracingNode, 'enabled', false);

        $profilingNode = $node->arrayNode('profiling')->addDefaultsIfNotSet()->children();
        $this->nodeDynamicEnabled($profilingNode, 'enabled', false);
        $profilingNode->floatNode('threshold')->defaultValue(ProfilerMiddlewarePass::PROFILER_THRESHOLD_DEFAULT)->min(ProfilerMiddlewarePass::PROFILER_THRESHOLD_MIN);
        $this->nodeStringList($profilingNode, 'allowed_types', ProfilerMiddlewarePass::PROFILER_ALLOWED_TYPES_DEFAULT);
        $this->nodeStringList($profilingNode, 'allowed_subtypes', ProfilerMiddlewarePass::PROFILER_IGNORED_TYPES_DEFAULT);
        $this->nodeStringList($profilingNode, 'ignored_types', ProfilerMiddlewarePass::PROFILER_ALLOWED_SUBTYPES_DEFAULT);
        $this->nodeStringList($profilingNode, 'ignored_subtypes', ProfilerMiddlewarePass::PROFILER_IGNORED_SUBTYPES_DEFAULT);

        return $tree;
    }

    private function nodeDynamicEnabled(NodeBuilder $node, string $name, bool $defaultValue): void
    {
        $node->variableNode($name)->defaultValue($defaultValue)
            ->validate()
                ->ifTrue(static function ($v): bool {
                    if (\is_bool($v)) {
                        return false;
                    }

                    if (!\is_string($v)) {
                        return true;
                    }

                    $v = \trim($v);

                    return $v === '';
                })
                ->thenInvalid("Invalid {$name} value. Should be boolean or a placeholder like %env(...)% / %parameter%.")
            ->end();
    }

    private function nodeStringList(NodeBuilder $node, string $name, ?array $defaultValue): void
    {
        $node->arrayNode($name)->defaultValue($defaultValue)->scalarPrototype()
            ->validate()
                ->ifTrue(static function ($v): bool {
                    if (!\is_string($v)) {
                        return true;
                    }

                    $v = \trim($v);

                    return $v === '';
                })
                ->thenInvalid("Invalid {$name} list value. Should be not empty string.")
            ->end();
    }
}
