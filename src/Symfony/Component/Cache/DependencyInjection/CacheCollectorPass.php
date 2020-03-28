<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\DependencyInjection;

use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Inject a data collector to all the cache services to be able to get detailed statistics.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CacheCollectorPass implements CompilerPassInterface
{
    private $dataCollectorCacheId;
    private $cachePoolTag;
    private $cachePoolRecorderInnerSuffix;

    public function __construct(string $dataCollectorCacheId = 'data_collector.cache', string $cachePoolTag = 'cache.pool', string $cachePoolRecorderInnerSuffix = '.recorder_inner')
    {
        $this->dataCollectorCacheId = $dataCollectorCacheId;
        $this->cachePoolTag = $cachePoolTag;
        $this->cachePoolRecorderInnerSuffix = $cachePoolRecorderInnerSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->dataCollectorCacheId)) {
            return;
        }

        $collectorDefinition = $container->getDefinition($this->dataCollectorCacheId);
        foreach ($container->findTaggedServiceIds($this->cachePoolTag) as $id => $attributes) {
            $definition = $container->getDefinition($id);
            if ($definition->isAbstract()) {
                continue;
            }

            $recorder = new Definition(TraceableAdapter::class);
            $recorder->setTags($definition->getTags());
            $definition->setTags([]);

            $recorder->setPublic($definition->isPublic());
            $recorder->setArguments([new Reference($innerId = $id.$this->cachePoolRecorderInnerSuffix)]);

            $definition->setPublic(false);

            $container->setDefinition($innerId, $definition);
            $container->setDefinition($id, $recorder);

            // Tell the collector to add the new instance
            $collectorDefinition->addMethodCall('addInstance', [$id, new Reference($id)]);
            $collectorDefinition->setPublic(false);

            if (strlen($id) > 6 && substr($id, -6) == '.inner') {
                $taggableId = substr($id, 1, -6);
            } else {
                $taggableId = sprintf('.%s.taggable', $id);
            }

            if ($this->registerTraceableTagAwareAdapter($container, $taggableId)) {
                // Tell the collector to add the new instance
                $collectorDefinition->addMethodCall('addInstance', [$taggableId, new Reference($taggableId)]);
            }
        }
    }

    private function registerTraceableTagAwareAdapter(ContainerBuilder $container, string $tagAwareAdapterId): bool
    {
        if (!$container->hasDefinition($tagAwareAdapterId)) {
            return false;
        }

        $tagAwareAdapterDef = $container->getDefinition($tagAwareAdapterId);
        if (!is_subclass_of($tagAwareAdapterDef->getClass(), TagAwareAdapterInterface::class)) {
            return false;
        }

        $tagAwareRecorderInnerId = $tagAwareAdapterId.$this->cachePoolRecorderInnerSuffix;
        $tagAwareRecorder = (new Definition(TraceableTagAwareAdapter::class))
            ->setPublic($tagAwareAdapterDef->isPublic())
            ->setArguments([new Reference($tagAwareRecorderInnerId)]);

        $tagAwareAdapterDef->setPublic(false);
        $container->setDefinition($tagAwareRecorderInnerId, $tagAwareAdapterDef);
        $container->setDefinition($tagAwareAdapterId, $tagAwareRecorder);

        return true;
    }
}
