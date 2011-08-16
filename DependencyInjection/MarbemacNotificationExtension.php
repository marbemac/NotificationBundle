<?php

namespace Marbemac\NotificationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;

class MarbemacNotificationExtension extends Extension
{
    protected $resources = array(
        'manager' => 'manager.xml'
    );

    public function load(array $configs, ContainerBuilder $container)
    {
        $this->loadDefaults($container);
        
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $variables = array(
                        'notification_manager',
                        'notification_class',
                        'user_route',
                        'user_route_parameter',
                        'max_contributor_show',
                    );

        foreach ($variables as $attribute) {
            $container->setParameter('marbemac_notification.options.'.$attribute, $config[$attribute]);
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/marbemac_notification';
    }

    /**
     * @codeCoverageIgnore
     */
    protected function loadDefaults($container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        
        foreach ($this->resources as $resource) {
            $loader->load($resource);
        }
    }
}