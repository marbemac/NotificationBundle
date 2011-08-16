<?php

namespace Marbemac\NotificationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder,
    Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('marbemac_notification');

        $rootNode
            ->children()
                ->scalarNode('notification_manager')->defaultValue('Marbemac\NotificationBundle\Document\NotificationManager')->cannotBeEmpty()->end()
                ->scalarNode('notification_class')->defaultValue('Marbemac\NotificationBundle\Document\Notification')->cannotBeEmpty()->end()
                ->scalarNode('user_route')->cannotBeEmpty()->end()
                ->scalarNode('user_route_parameter')->cannotBeEmpty()->end()
                ->scalarNode('max_contributor_show')->cannotBeEmpty()->end()
            ->end();

        return $treeBuilder;
    }

}