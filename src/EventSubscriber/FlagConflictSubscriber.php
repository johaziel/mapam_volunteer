<?php

namespace Drupal\mapam_volunteer\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Event\FlagEvents as Flag;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Database\Connection;

/**
 * Event subscriber for flag conflicts.
 *
 * Handles conflict detection when flagging volunteer slots.
 */
class FlagConflictSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(AccountInterface $current_user, FlagServiceInterface $flag_service, StateInterface $state, Connection $connection) {
    $this->currentUser = $current_user;
    $this->flagService = $flag_service;
    $this->state = $state;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[Flag::ENTITY_FLAGGED] = ['onFlag', 50];
    // $events[Flag::ENTITY_UNFLAGGED] = ['onUnflag', 50];
    return $events;
  }

  /**
   * React to flagging event.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function onFlag(FlaggingEvent $event) {

    // @todo check ddl($flag->getLinkTypePlugin());
    $flagging = $event->getFlagging();
    $flag = $flagging->getFlag();

    $entity = $flagging->getFlaggable();
    $user = $flagging->getOwner();

    // Manage only volunteer flag.
    if ($flagging->getFlagId() !== 'volunteer') {
      return;
    }

    $field_name = 'field_smart_date';

    if (!$entity->hasField($field_name)) {
      return;
    }

    $item_start = $entity->get($field_name)->value;
    $item_end = $entity->get($field_name)->end_value;

    $item_date_start = new DrupalDateTime($item_start);
    $item_db_start = $item_date_start->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $item_date_end = new DrupalDateTime($item_end);
    $item_db_end = $item_date_end->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = $this->connection->select('flagging', 'f')
      ->fields('f', ['entity_id'])
      ->condition('f.id', $flagging->id(), '<>')
      ->condition('f.flag_id', $flag->id())
      ->condition('f.uid', $user->id());
    $query->leftJoin('node__' . $field_name, 'n', 'n.entity_id = f.entity_id');
    $query->condition('n.' . $field_name . '_value', $item_db_end, '<=');
    $query->condition('n.' . $field_name . '_end_value', $item_db_start, '>=');

    $conflicts = $query->execute()->fetchCol();

    if (!empty($conflicts)) {
      $flagging->delete();
      $event->stopPropagation();

      if ($this->currentUser->id() == $user->id()) {
        $this->messenger()->addError(
          $this->t("You are already registred on another schedule item at the same time."));
      }
      else {
        $this->messenger()->addError(
          $this->t("%user_name is already registred on another schedule item at the same time.",
          ['%user_name' => $user->getAccountName()]));
      }

    }

  }

}
