<?php

namespace Drupal\mapam_volunteer\Plugin\ParentEventTarget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\mapam_volunteer\Plugin\ParentEventTargetBase;

/**
 * Provides default user source (all users).
 *
 * @ParentEventTarget(
 *   id = "field",
 *   label = @Translation("With a field"),
 *   description = @Translation("Event with a field."),
 *   priority = -50
 * )
 */
class FieldParentEventTarget extends ParentEventTargetBase {

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity) {
    // This is the fallback, always applies.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEventTarget(EntityInterface $entity) {
    return mapam_volunteer_get_parent_entity($entity);
  }

}
