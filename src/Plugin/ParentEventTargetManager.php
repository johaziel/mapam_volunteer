<?php

namespace Drupal\mapam_volunteer\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages User Source plugins.
 */
class ParentEventTargetManager extends DefaultPluginManager {

  /**
   * Constructs a ParentEventTargetManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ParentEventTarget',
      $namespaces,
      $module_handler,
      'Drupal\mapam_volunteer\Plugin\ParentEventTargetInterface',
      'Drupal\mapam_volunteer\Annotation\ParentEventTarget'
    );
    $this->alterInfo('parent_event_target_info');
    $this->setCacheBackend($cache_backend, 'parent_event_target_plugins');
  }

  /**
   * Gets the applicable user source for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being flagged.
   *
   * @return \Drupal\mapam_volunteer\Plugin\ParentEventTargetInterface|null
   *   The applicable user source plugin or NULL.
   */
  public function getApplicableTarget(EntityInterface $entity) {
    $definitions = $this->getDefinitions();

    // Sort by priority (descending).
    uasort($definitions, function ($a, $b) {
      return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
    });

    foreach ($definitions as $plugin_id => $definition) {
      /** @var \Drupal\mapam_volunteer\Plugin\ParentEventTargetInterface $plugin */
      $plugin = $this->createInstance($plugin_id);
      if ($plugin->applies($entity)) {
        return $plugin;
      }
    }

    return NULL;
  }

}
