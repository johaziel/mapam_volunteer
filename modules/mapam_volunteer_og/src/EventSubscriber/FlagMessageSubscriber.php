<?php

namespace Drupal\mapam_volunteer_og\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\Event\FlagEvents as Flag;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\Core\Database\Connection;
use Drupal\flag\Entity\Flagging;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifyInterface;

/**
 * Event subscriber for flag conflicts.
 *
 * Handles conflict detection when flagging volunteer slots.
 */
class FlagMessageSubscriber implements EventSubscriberInterface {

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
    $events[Flag::ENTITY_FLAGGED] = ['onFlag', 50];
    $events[Flag::ENTITY_UNFLAGGED] = ['onUnflag', 50];
    return $events;
  }

  /**
   * React to flagging event.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function onFlag(FlaggingEvent $event) {
    $flagging = $event->getFlagging();
    // Manage only volunteer flag.
    if ($flagging->getFlagId() !== 'volunteer') {
      return;
    }

    $this->sendNotificationsFromFlagging($flagging, 'flag');

  }

  /**
   * React to unflagging event.
   *
   * @param \Drupal\flag\Event\UnflaggingEvent $event
   *   The unflagging event.
   */
  public function onUnflag(UnflaggingEvent $event) {
    $flaggings = $event->getFlaggings();

    foreach ($flaggings as $flagging) {
      // Manage only volunteer flag.
      if ($flagging->getFlagId() !== 'volunteer') {
        continue;
      }

      $this->sendNotificationsFromFlagging($flagging, 'unflag');
    }
  }

  /**
   * Helper function to send notifications from flagging.
   *
   * @param \Drupal\flag\Entity\Flagging $flagging
   *   The flagging entity.
   * @param string $action
   *   The action performed (flag or unflag).
   */
  protected function sendNotificationsFromFlagging(Flagging $flagging, string $action) {
    $node = $flagging->getFlaggable();

    // Get the OG group (pole) to which the flagged timeslot belongs.
    /** @var \Drupal\og\OgGroupAudienceHelperInterface $og_helper */
    $og_poles = $this->membershipManager->getGroups($node);

    // Only one pole is expected.
    $og_pole = reset($og_poles['node']);
    if (!$og_pole) {
      return;
    }

    // Get event from the pole .
    /** @var \Drupal\og\OgGroupAudienceHelperInterface $og_helper */
    $og_events = $this->membershipManager->getGroups($og_pole);
    // Only one pole is expected.
    $og_event = reset($og_events['node']);

    // Merge the pole and the event to get all the groups to notify.
    $og_groups = [];

    if ($og_pole) {
      $og_groups['node'][$og_pole->id()] = $og_pole;
    }
    if ($og_event) {
      $og_groups['node'][$og_event->id()] = $og_event;
    }

    if (!$og_groups) {
      return;
    }

    // Get unique groups administrators and referents to notify.
    $unique_members = [];
    foreach ($og_groups as $groups) {
      foreach ($groups as $group) {

        // Get the memberships of the group with administrator or referent role.
        $memberships = $this->membershipManager
          ->getGroupMembershipsByRoleNames($group, ['administrator', 'referent'], [OgMembershipInterface::STATE_ACTIVE]);

        foreach ($memberships as $membership) {
          $user = $membership->getOwner();

          // Avoid sending notification to the user who performed the action.
          if ($user->id() === $flagging->getOwnerId()) {
            continue;
          }

          $unique_members[$user->id()] = $user;
        }
      }
    }

    foreach ($unique_members as $member) {
      // Avoid sending notification to the user who performed the action.
      if ($member->id() === $flagging->getOwnerId()) {
        continue;
      }

      // Avoid sending notification if opposite action is already performed
      // Eg: if an user flag and unflag quicly, don't send notification.
      // Delete the opposite message if it exists.
      $opposite_action = $action === 'flag' ? 'unflag' : 'flag';
      $opposite_messages = $this->entityTypeManager->getStorage('message')->loadByProperties([
        'template' => $opposite_action . '_notify',
        'uid' => $member->id(),
        'field_timeslot' => $node->id(),
        'field_user' => $flagging->getOwnerId(),
      ]);

      if (!empty($opposite_messages)) {
        foreach ($opposite_messages as $opposite_message) {
          $opposite_message->delete();
        }
        continue;
      }
      else {
        // Create a message for the member.
        $message = Message::create([
          'template' => $action . '_notify',
          'uid' => $member->id(),
          'entity_id' => $flagging->id(),
          'entity_type' => 'flagging',
        ]);
        $message->set('field_timeslot', $node->id());
        $message->set('field_pole', $og_pole->id());
        $message->set('field_event', $og_event->id());
        $message->set('field_user', $flagging->getOwnerId());
        $message->set('field_action', $action);
        $message->save();

        // Send the digest message to the member.
        /** @var \Drupal\message_notify\MessageNotifier $notifier */
        $this->messageNotifier->send($message, [], 'message_digest:daily');
      }

    }
  }

}
