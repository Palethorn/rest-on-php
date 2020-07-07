<?php

namespace RestOnPhp\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class LoggerPass implements CompilerPassInterface {
    public function process(ContainerBuilder $container) {
        if (!$container->has('api.logger')) {
            return;
        }

        $definition = $container->findDefinition('api.logger');
        $taggedServices = $container->findTaggedServiceIds('api.logger.processor');

        foreach($taggedServices as $id => $tags) {
            $definition->addMethodCall('pushProcessor', [new Reference($id)]);
        }

        $taggedServices = $container->findTaggedServiceIds('api.logger.handler');

        foreach($taggedServices as $id => $tags) {
            $definition->addMethodCall('pushHandler', [new Reference($id)]);
        }
    }
}
