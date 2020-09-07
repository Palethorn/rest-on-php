<?php

namespace RestOnPhp\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EventPass implements CompilerPassInterface {
    public function process(ContainerBuilder $container) {
        if (!$container->has('api.event.dispatcher')) {
            return;
        }

        $definition = $container->findDefinition('api.event.dispatcher');
        $taggedServices = $container->findTaggedServiceIds('api.event.listeners');

        foreach($taggedServices as $id => $tags) {
            foreach($tags as $tag) {
                $definition->addMethodCall('addListener', [$tag['event'], [new Reference($id), 'handle']]);
            }
        }
    }
}
