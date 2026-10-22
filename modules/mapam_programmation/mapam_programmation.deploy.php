<?php

/**
 * @file
 * Deploy functions for Mapam Programmation, run by "drush deploy" after the
 * configuration import.
 */

/**
 * Links the existing sessions to the current event.
 */
function mapam_programmation_deploy_session_event(&$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $event = \Drupal::service('mapam_volunteer.current_event')->getCurrent();
  $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'session');
  if (!$event || !isset($fields['field_event'])) {
    return t('No current event or no event field on sessions: nothing to do.');
  }

  if (!isset($sandbox['ids'])) {
    $sandbox['ids'] = array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'session')
      ->notExists('field_event')
      ->execute());
    $sandbox['total'] = count($sandbox['ids']);
  }

  foreach ($storage->loadMultiple(array_splice($sandbox['ids'], 0, 50)) as $session) {
    $session->set('field_event', $event->id())->save();
  }

  $sandbox['#finished'] = $sandbox['total'] ? 1 - count($sandbox['ids']) / $sandbox['total'] : 1;
  return t('@count sessions linked to @event.', ['@count' => $sandbox['total'], '@event' => $event->label()]);
}
