<?php

namespace Drupal\mapam_volunteer_og\EventSubscriber;

/**
 * @file
 * Contains \Drupal\mapam_volunteer_og\EventSubscriber\TimeslotSubscriber.
 */

use Drupal\core_event_dispatcher\EntityHookEvents;
use Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\core_event_dispatcher\Event\Entity\EntityUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

/**
 * Subscribe to entity insert and update to react to timeslot node changes.
 */
class TimeslotSubscriber implements EventSubscriberInterface {

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
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface|null
   */
  protected $membershipManager;

  /**
   * The message notification service.
   *
   * @var \Drupal\message_notify\MessageNotifyInterface
   */
  protected $messageNotifier;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The OG membership manager.
   * @param \Drupal\message_notify\MessageNotifyInterface $message_notify
   *   The message notification service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, FlagServiceInterface $flag_service, MembershipManagerInterface $membership_manager, MessageNotifyInterface $message_notify, Connection $database) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->flagService = $flag_service;
    $this->membershipManager = $membership_manager;
    $this->messageNotifier = $message_notify;
    $this->database = $database;
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
    if ($entity->bundle() !== 'timeslot') {
      return;
    }

    $this->sendTimeslotNotificationMessage($entity, 'insert');
  }

  /**
   * Send a message to member when a timeslot created.
   *
   * @param \Drupal\core_event_dispatcher\Event\Entity\EntityInsertEvent $event
   *   The entity insert event.
   */
  public function onEntityUpdate(EntityUpdateEvent $event) {
    $entity = $event->getEntity();

    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }
    if ($entity->bundle() !== 'timeslot') {
      return;
    }

    $this->sendTimeslotNotificationMessage($entity, 'update');
  }

  /**
   * Helper function to send the message to the member.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The timeslot entity.
   * @param string $action
   *   The action performed on the timeslot (insert or update).
   */
  protected function sendTimeslotNotificationMessage(EntityInterface $entity, string $action) {
    // Get all user from the parent group.
    $flagging_users = $this->getFlaggingUsersFromParentGroup($entity);

    foreach ($flagging_users as $user_id) {

      // Check if there is a message.
      $messages = $this->entityTypeManager->getStorage('message')->loadByProperties([
        'template' => 'timeslot_notification',
        'uid' => $user_id,
        'field_timeslot' => $entity->id(),
      ]);

      if (!empty($messages)) {
        foreach ($messages as $message) {
          // Check if the message is already in the digest queue.
          $already_queued = $this->database->select('message_digest', 'md')
            ->fields('md', ['id'])
            ->condition('mid', $message->id())
            ->condition('receiver', $user_id)
            ->condition('sent', 0)
            ->execute()
            ->fetchField();

          if (!$already_queued) {
            // Create a message for the member.
            $message = Message::create([
              'template' => 'timeslot_notification',
              'uid' => $user_id,
              'field_timeslot' => $entity->id(),
            ]);
            $message->save();

            // Send the digest message to the member.
            /** @var \Drupal\message_notify\MessageNotifier $notifier */
            $this->messageNotifier->send($message, [], 'message_digest:daily');
          }
        }
      }
      else {
        // Create a message for the member.
        $message = Message::create([
          'template' => 'timeslot_notification',
          'uid' => $user_id,
          'field_timeslot' => $entity->id(),
        ]);
        $message->save();

        // Send the digest message to the member.
        /** @var \Drupal\message_notify\MessageNotifier $notifier */
        $this->messageNotifier->send($message, [], 'message_digest:daily');
      }
    }
  }

  /**
   * Return the parent group of the given entity.
   */
  protected function getParentGroup(EntityInterface $entity) {
    $groups = $this->membershipManager->getGroups($entity);
    // The groups are returned as a two-dimensional array. Flatten it.
    $groups = array_reduce($groups, 'array_merge', []);
    // Get the first group (if any).
    $group = reset($groups);

    return $group;
  }

  /**
   * Recursively finds the parent group of the given entity.
   */
  protected function findGrandParentGroup(EntityInterface $entity) {
    $groups = $this->membershipManager->getGroups($entity);
    // The groups are returned as a two-dimensional array. Flatten it.
    $groups = array_reduce($groups, 'array_merge', []);
    // Get the first group (if any).
    $group = reset($groups);

    if ($group && $group->bundle() === 'event') {
      $this->parentGroup = $group;
      return;
    }
    $this->findGrandParentGroup($group);

  }

  /**
   * Get all volonteer from the parent group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group entity.
   */
  protected function getFlaggingUsersFromParentGroup(EntityInterface $entity) {

    // Get the parent group of the entity.
    $parent_group = $this->getParentGroup($entity);
    if (!$parent_group) {
      return [];
    }

    // Get all content of the parent group.
    $group_contents = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'timeslot',
      'og_audience' => $parent_group->id(),
    ]);

    $flagging_users = [];
    foreach ($group_contents as $content_entity) {
      // Get all user who flagged this content as volunteer.
      $flaggings = $this->flagService->getAllEntityFlaggings($content_entity);

      foreach ($flaggings as $flagging) {
        // Check if user flagged as notificiation flag for the parent group.
        $flag_timeslot_notification = $this->flagService->getFlagById('timeslot_notification');
        $notif_flag = $this->flagService->getFlagging($flag_timeslot_notification, $parent_group, $flagging->getOwner());

        if ($notif_flag) {
          $flagging_users[] = $flagging->getOwnerId();
        }
      }

    }

    return array_unique($flagging_users);
  }

}
