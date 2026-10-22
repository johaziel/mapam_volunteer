<?php

namespace Drupal\mapam_volunteer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Flag service applying the registration rules of volunteers.
 *
 * Replaces the class of the "flag" service, see MapamVolunteerServiceProvider.
 * The rules are checked when flagging, not in the access of the flag links:
 * the links keep being displayed with their "full" or "deadline passed"
 * state. Refused actions throw a \LogicException, which the flag action link
 * controller catches before displaying the link again.
 */
class VolunteerFlagService extends FlagService {

  public function __construct(
    AccountProxyInterface $currentUser,
    EntityTypeManagerInterface $entityTypeManager,
    ?RequestStack $requestStack,
    #[Autowire(service: 'mapam_volunteer.registration_validator')]
    protected RegistrationValidator $registrationValidator,
    #[Autowire(service: 'lock')]
    protected LockBackendInterface $lock,
    #[Autowire(service: 'messenger')]
    protected MessengerInterface $messenger,
  ) {
    parent::__construct($currentUser, $entityTypeManager, $requestStack);
  }

  /**
   * {@inheritdoc}
   */
  public function flag(FlagInterface $flag, EntityInterface $entity, ?AccountInterface $account = NULL, $session_id = NULL) {
    if ($flag->id() !== RegistrationValidator::FLAG_ID) {
      return parent::flag($flag, $entity, $account, $session_id);
    }

    // Two volunteers must not take the last spot at the same time.
    $lock_name = 'mapam_volunteer_registration:' . $entity->getEntityTypeId() . ':' . $entity->id();
    if (!$this->lock->acquire($lock_name)) {
      $this->lock->wait($lock_name);
      if (!$this->lock->acquire($lock_name)) {
        $this->refuse([new TranslatableMarkup('The timeslot is busy, please try again.')]);
      }
    }
    try {
      $user = $account ?? $this->currentUser;
      if (!$flag->isFlagged($entity, $user)) {
        $this->refuse($this->registrationValidator->validateRegistration($entity, [$user], $this->currentUser));
      }
      return parent::flag($flag, $entity, $account, $session_id);
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unflag(FlagInterface $flag, EntityInterface $entity, ?AccountInterface $account = NULL, $session_id = NULL) {
    if ($flag->id() === RegistrationValidator::FLAG_ID) {
      $this->refuse($this->registrationValidator->validateUnregistration($entity, $this->currentUser));
    }
    parent::unflag($flag, $entity, $account, $session_id);
  }

  /**
   * Displays the reasons of a refused action and stops it.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $errors
   *   The reasons, nothing happens when there are none.
   *
   * @throws \LogicException
   */
  protected function refuse(array $errors): void {
    if (!$errors) {
      return;
    }
    foreach ($errors as $error) {
      $this->messenger->addError($error);
    }
    throw new \LogicException(implode(' ', array_map('strval', $errors)));
  }

}
