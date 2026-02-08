<?php

namespace Drupal\mapam_volunteer\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag_fse\Event\FlagFseAddUserEvent;
use Drupal\flag_fse\Event\FlagFseUserEvents;

/**
 * Event subscriber for handling new user events in the Flag FSE module.
 *
 * This subscriber listens for the event when a new user is added to the system
 * and performs necessary actions related to the Flag FSE module.
 */
class FlagFseNewUserSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\user\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      FlagFseUserEvents::ADD_USER => 'onAddUser',
    ];
  }

  /**
   * Responds to the event when a new user is added to the system.
   *
   * @param \Drupal\flag_fse\Event\FlagFseAddUserEvent $event
   *   The event object containing information about the new user.
   */
  public function onAddUser(FlagFseAddUserEvent $event) {
    $user = $event->getUser();
    $roleStorage = $this->entityTypeManager->getStorage('user_role');
    // Check if the 'volunteer' role exists before adding it to the user.
    if (!$roleStorage->load('volunteer')) {
      return;
    }
    // Add vonteer role to the new user.
    $user->addRole('volunteer');
  }

}
