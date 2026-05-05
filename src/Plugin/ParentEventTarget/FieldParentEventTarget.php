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
    // Use the back reference service to find the parent event.
    ddl('getParentEventTarget');
    $back_reference_service = \Drupal::service('entity_back_reference.back_reference_finder');
    $back_ref_field = $back_reference_service->getReferencingFieldList('node', $entity->bundle());

    if (!empty($back_ref_field)) {
      // Loop through all the field config where our entity has been referenced.
      foreach ($back_ref_field as $fieldConfig) {
        // Load all entities referencing the volunteer slot.
        $referencing_entities = $back_reference_service->loadBackReferencedEntities($fieldConfig, $entity->id());
      }
    }

    // Filter to find the parent event node.
    foreach ($referencing_entities as $referencing_entity) {
      if ($referencing_entity->getEntityTypeId() === 'node' &&
        $referencing_entity->bundle() === 'event') {
        return $referencing_entity;
      }
    }

    return NULL;
  }

}
