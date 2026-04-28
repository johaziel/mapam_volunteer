<?php

namespace Drupal\mapam_volunteer\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for Parent Event Target .
 */
interface ParentEventTargetInterface extends PluginInspectionInterface {

  /**
   * Gets the parent event target based on the context.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being flagged.
   *
   * @return array
   *   Array of user IDs that should be available for selection.
   */
  public function getParentEventTarget(EntityInterface $entity);

  /**
   * Gets the description to display when this source is active.
   *
   * @return string|null
   *   A description string or NULL.
   */
  public function getDescription();

  /**
   * Checks if this source applies to the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if this source applies, FALSE otherwise.
   */
  public function applies(EntityInterface $entity);

  /**
   * Gets the priority/weight of this source.
   *
   * Higher priority sources are used first.
   *
   * @return int
   *   The priority (higher = more important).
   */
  public function getPriority();

}
