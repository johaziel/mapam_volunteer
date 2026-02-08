<?php

namespace Drupal\mapam_volunteer\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\flag\Event\FlagEvents as Flag;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Event subscriber for handling volunteer flag unflagging with date limits.
 */
class UnFlagDateLimitSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The current user injected into the service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
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
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor.
   *
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(FlagServiceInterface $flag_service, StateInterface $state, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->flagService = $flag_service;
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[Flag::ENTITY_UNFLAGGED] = ['onUnflag', 50];
    // $events[Flag::ENTITY_FLAGGED] = ['onFlag', 50];
    return $events;
  }

  /**
   * React to unflagging event.
   *
   * @param \Drupal\flag\Event\UnflaggingEvent $event
   *   The unflagging event.
   */
  public function onUnflag(UnflaggingEvent $event) {

    $flagging = reset($event->getFlaggings());

    $flag = $flagging->getFlag();

    $schedule_item = $flagging->getFlaggable();
    $user = $flagging->getOwner();

    // Ne gérer que le flag "volunteer".
    if ($flagging->getFlagId() !== 'volunteer') {
      return;
    }

    $schedule = $schedule_item->getSchedule();
    $entity = $schedule->getHostEntity()->getEntity();

    // Check if entity has field 'field_date_limit_unflag'.
    if (!$entity->hasField('field_date_limit_unflag')) {
      return;
    }

    // Check if now is up to field_date_limit_unflag.
    $limit_date = (int) $entity->get('field_date_limit_unflag')->value;

    if ($limit_date) {
      $now = new DrupalDateTime();
      if ($now->getTimestamp() < $limit_date) {
        return;
      }
    }
    /*
    ddl([
      'uid' => $this->currentUser->id(),
      'session_id' => $session_id,
      'flag_id' => $flag->id(),
      'entity_id' => $schedule_item->id(),
      'entity_type' => $schedule_item->getEntityTypeId(),
      'global' => $flag->isGlobal(),
    ]);
    
    $new_flagging = $this->entityTypeManager->getStorage('flagging')->create([
      'uid' => $this->currentUser->id(),
      'session_id' => $session_id,
      'flag_id' => $flag->id(),
      'entity_id' => $schedule_item->id(),
      'entity_type' => $schedule_item->getEntityTypeId(),
      'global' => $flag->isGlobal(),
    ]);

    $new_flagging->save();
    

    $link_type_plugin = $flag->getLinkTypePlugin();

    if ($link_type_plugin->getPluginId() == 'ajax_link') {
      $response = new AjaxResponse();
      $response->addCommand(new MessageCommand($this->t("The date limit to unflag is past, you can not unflag this entity."), NULL, ['type' => 'error']));
      return $response;
    }
    */
    $event->stopPropagation();

    $this->messenger()->addError($this->t("The date limit to unflag is past, you can not unflag this entity."));

  }

}
