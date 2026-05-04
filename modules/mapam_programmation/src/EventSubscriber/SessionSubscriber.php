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
   * Check if the session has volunteer needs.
   *
   * From Inline Entity Form in session (field_volunteer_needs)
   * a timeslot is register with just flag_limit and pole fields.
   *
   * So check if session has field_volunteer_needs field with values.
   * Load the timeslot entity and copy the body and the field_smart_date
   * from the session.
   *
   * @param \Drupal\Core\Entity\EntityInterface $session
   *   The session node entity.
   * @param string $action
   *   The action that triggered the check, either 'insert' or 'update'.
   */
  protected function checkVolunteerNeeds(EntityInterface $session, string $action) {
    // Check if the session has volunteer needs.
    if (!$session->hasField('field_volunteer_needs') || $session->get('field_volunteer_needs')->isEmpty()) {
      return;
    }

    // Load the timeslot entity from the field_volunteer_needs reference.
    $timeslot_ref = $session->get('field_volunteer_needs')->entity;

    if (!$timeslot_ref) {
      return;
    }

    // Copy the body and field_smart_date from the session to the timeslot.
    if ($session->hasField('body') && !$session->get('body')->isEmpty()) {
      $timeslot_ref->set('body', [
        'value'  => $session->get('body')->value . "\n\n" . ($timeslot_ref->get('body')->value ?? NULL),
        'format' => $session->get('body')->format,
      ]);
    }

    if ($session->hasField('field_smart_date') && !$session->get('field_smart_date')->isEmpty()) {
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
      $timeslot_ref->set('field_smart_date', $values);
    }

    // Save the timeslot entity to persist the changes.
    $timeslot_ref->save();
  }

}
