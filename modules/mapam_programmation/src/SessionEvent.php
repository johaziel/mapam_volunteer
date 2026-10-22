<?php

namespace Drupal\mapam_programmation;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Links the sessions of the program to the festival event.
 *
 * The "field_event" field of sessions is not displayed in the forms: new
 * sessions belong to the current event.
 */
class SessionEvent {

  /**
   * Default value callback of the event of sessions: the current event.
   *
   * @return array
   *   The field value.
   */
  public static function defaultValue(FieldableEntityInterface $entity, FieldDefinitionInterface $definition): array {
    $event = \Drupal::service('mapam_volunteer.current_event')->getCurrent();
    return $event ? [['target_id' => $event->id()]] : [];
  }

}
