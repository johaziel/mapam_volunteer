<?php

namespace Drupal\mapam_volunteer\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Base class for Parent Event Target plugins.
 */
abstract class ParentEventTargetBase extends PluginBase implements ParentEventTargetInterface {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority() {
    return $this->pluginDefinition['priority'] ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function applies(EntityInterface $entity);

  /**
   * {@inheritdoc}
   */
  abstract public function getParentEventTarget(EntityInterface $entity);

}
