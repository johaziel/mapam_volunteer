<?php

namespace Drupal\mapam_programmation\EventSubscriber;

/**
 * @file
 * Contains \Drupal\mapam_programmation\EventSubscriber\SessionSubscriber.
 */

use Drupal\core_event_dispatcher\EntityHookEvents;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Subscribe to entity insert and update to react to session node changes.
 */
class SessionSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityHookEvents::ENTITY_INSERT => 'onEntityInsert',
      EntityHookEvents::ENTITY_UPDATE => 'onEntityUpdate',
    ];
  }

  /**
   * Send a message to member when a timeslot created.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent $event
   *   The entity insert event.
   */
  public function onEntityInsert(EntityInsertEvent $event) {
    $entity = $event->getEntity();

    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }
    if ($entity->bundle() !== 'session') {
      return;
    }

    $this->checkVolunteerNeeds($entity, 'insert');
  }

  /**
   * Send a message to member when a session updated.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent $event
   *   The entity insert event.
   */
  public function onEntityUpdate(EntityUpdateEvent $event) {
    $entity = $event->getEntity();

    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }
    if ($entity->bundle() !== 'session') {
      return;
    }

    $this->checkVolunteerNeeds($entity, 'update');
  }

  /**
   * Copies the dates and the description of a session to its volunteer need.
   *
   * The timeslot of the volunteer need follows the dates of the session. Its
   * description follows the description of the session, unless it was changed
   * on the timeslot itself. A timeslot shared by several sessions is left
   * untouched: the sessions would overwrite each other.
   *
   * @param \Drupal\Core\Entity\EntityInterface $session
   *   The session.
   * @param string $action
   *   Either "insert" or "update".
   */
  protected function checkVolunteerNeeds(EntityInterface $session, string $action) {
    if (!$session->hasField('field_volunteer_needs') || $session->get('field_volunteer_needs')->isEmpty()) {
      return;
    }
    // Load the timeslot from the database: the referenced entity cached on the
    // session may be outdated, and saving it would revert later changes.
    $timeslot = $this->entityTypeManager->getStorage('node')->loadUnchanged($session->get('field_volunteer_needs')->target_id);
    if (!$timeslot) {
      return;
    }

    $other_sessions = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'session')
      ->condition('field_volunteer_needs', $timeslot->id())
      ->condition('nid', $session->id(), '<>')
      ->range(0, 1)
      ->execute();
    if ($other_sessions) {
      $this->messenger()->addWarning($this->t('The dates of %timeslot were not updated: it is the volunteer need of several sessions.', ['%timeslot' => $timeslot->label()]));
      return;
    }

    $changed = FALSE;
    $original = $action === 'update' ? (method_exists($session, 'getOriginal') ? $session->getOriginal() : $session->original) : NULL;

    // The description, unless it was changed on the timeslot.
    if ($session->hasField('body') && $timeslot->hasField('body') && !$session->get('body')->isEmpty()) {
      $timeslot_body = $timeslot->get('body')->value;
      $previous_body = $original && $original->hasField('body') ? $original->get('body')->value : NULL;
      $follows_session = $timeslot->get('body')->isEmpty() || $timeslot_body === $previous_body;
      if ($follows_session && $timeslot_body !== $session->get('body')->value) {
        $timeslot->set('body', [
          'value'  => $session->get('body')->value,
          'format' => $session->get('body')->format,
        ]);
        $changed = TRUE;
      }
    }

    // The dates.
    if ($session->hasField('field_smart_date') && $timeslot->hasField('field_smart_date') && !$session->get('field_smart_date')->isEmpty()) {
      $values = [];
      foreach ($session->get('field_smart_date') as $item) {
        $values[] = [
          'value'       => $item->value,
          'end_value'   => $item->end_value,
          'duration'    => $item->duration,
          'rrule'       => $item->rrule ?? NULL,
          'rrule_index' => $item->rrule_index ?? NULL,
          'timezone'    => $item->timezone ?? NULL,
        ];
      }
      $current = array_map(fn ($item) => [(int) $item['value'], (int) $item['end_value']], $timeslot->get('field_smart_date')->getValue());
      if ($current !== array_map(fn ($item) => [(int) $item['value'], (int) $item['end_value']], $values)) {
        $timeslot->set('field_smart_date', $values);
        $changed = TRUE;
      }
    }

    if ($changed) {
      $timeslot->save();
    }
  }

}
