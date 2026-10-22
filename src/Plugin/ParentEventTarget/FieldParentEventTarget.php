<?php

namespace Drupal\mapam_volunteer\Plugin\ParentEventTarget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\mapam_volunteer\Plugin\ParentEventTargetBase;

/**
 * Finds the event of a timeslot or a session with its "field_event" field.
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
    if ($entity instanceof FieldableEntityInterface && $entity->hasField('field_event')) {
      return $entity->get('field_event')->entity;
    }
    return NULL;
  }

}
