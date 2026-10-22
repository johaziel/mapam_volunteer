<?php

namespace Drupal\mapam_volunteer;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mapam_volunteer\Plugin\ParentEventTargetManager;

/**
 * Checks the rules of the registrations of volunteers on timeslots.
 *
 * - Registrations close at the "field_date_limit_flag" date of the event,
 *   unregistrations at its "field_date_limit_unflag" date.
 * - A timeslot accepts at most "field_flag_limit" volunteers.
 * - A volunteer cannot be registered on two overlapping timeslots.
 *
 * Users with the "administer flaggings" permission may register volunteers
 * after the deadlines and beyond the limit, but never on overlapping
 * timeslots.
 */
class RegistrationValidator {

  use StringTranslationTrait;

  /**
   * The ID of the registration flag.
   */
  public const FLAG_ID = 'volunteer';

  /**
   * The permission to bypass the deadlines and the limit of volunteers.
   */
  public const BYPASS_PERMISSION = 'administer flaggings';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ParentEventTargetManager $parentEventTargetManager,
    protected TimeInterface $time,
  ) {}

  /**
   * Gets the event of a timeslot.
   */
  public function getEvent(EntityInterface $timeslot): ?EntityInterface {
    if ($timeslot->hasField('field_event') && ($event = $timeslot->get('field_event')->entity)) {
      return $event;
    }
    // Other structures, e.g. Organic Groups, provide their own plugin.
    return $this->parentEventTargetManager->getApplicableTarget($timeslot)?->getParentEventTarget($timeslot);
  }

  /**
   * Gets the deadline of an action on a timeslot.
   *
   * @param \Drupal\Core\Entity\EntityInterface $timeslot
   *   The timeslot.
   * @param string $action
   *   Either "flag" or "unflag".
   *
   * @return int|null
   *   The deadline timestamp, or NULL when there is none.
   */
  public function getDeadline(EntityInterface $timeslot, string $action): ?int {
    $field_name = $action === 'unflag' ? 'field_date_limit_unflag' : 'field_date_limit_flag';
    $event = $this->getEvent($timeslot);
    if (!$event || !$event->hasField($field_name) || $event->get($field_name)->isEmpty()) {
      return NULL;
    }
    return (int) $event->get($field_name)->value;
  }

  /**
   * Checks whether the deadline of an action on a timeslot has passed.
   */
  public function isDeadlinePassed(EntityInterface $timeslot, string $action): bool {
    $deadline = $this->getDeadline($timeslot, $action);
    return $deadline !== NULL && $this->time->getRequestTime() > $deadline;
  }

  /**
   * Gets the number of volunteers registered on a timeslot.
   */
  public function getRegistrationCount(EntityInterface $timeslot): int {
    return (int) $this->entityTypeManager->getStorage('flagging')->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', self::FLAG_ID)
      ->condition('entity_type', $timeslot->getEntityTypeId())
      ->condition('entity_id', $timeslot->id())
      ->count()
      ->execute();
  }

  /**
   * Gets the limit of volunteers of a timeslot.
   *
   * @return int|null
   *   The limit, or NULL when the number of volunteers is not limited.
   */
  public function getLimit(EntityInterface $timeslot): ?int {
    if (!$timeslot->hasField('field_flag_limit') || $timeslot->get('field_flag_limit')->isEmpty()) {
      return NULL;
    }
    $limit = (int) $timeslot->get('field_flag_limit')->value;
    return $limit > 0 ? $limit : NULL;
  }

  /**
   * Gets the remaining places of a timeslot.
   *
   * @return int|null
   *   The number of remaining places, or NULL when it is not limited.
   */
  public function getRemainingPlaces(EntityInterface $timeslot): ?int {
    $limit = $this->getLimit($timeslot);
    return $limit === NULL ? NULL : max(0, $limit - $this->getRegistrationCount($timeslot));
  }

  /**
   * Gets a timeslot of a user overlapping the given timeslot.
   *
   * Timeslots that only touch, e.g. 14:00-17:00 and 17:00-20:00, do not
   * overlap.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   A timeslot the user is registered on at the same time, if any.
   */
  public function getOverlappingTimeslot(EntityInterface $timeslot, AccountInterface $user): ?EntityInterface {
    if (!$timeslot->hasField('field_smart_date') || $timeslot->get('field_smart_date')->isEmpty()) {
      return NULL;
    }
    $date = $timeslot->get('field_smart_date')->first();

    $flagging_storage = $this->entityTypeManager->getStorage('flagging');
    $flagging_ids = $flagging_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', self::FLAG_ID)
      ->condition('entity_type', $timeslot->getEntityTypeId())
      ->condition('uid', $user->id())
      ->condition('entity_id', $timeslot->id(), '<>')
      ->execute();
    if (!$flagging_ids) {
      return NULL;
    }
    $timeslot_ids = array_map(fn ($flagging) => $flagging->get('entity_id')->value, $flagging_storage->loadMultiple($flagging_ids));

    $storage = $this->entityTypeManager->getStorage($timeslot->getEntityTypeId());
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($storage->getEntityType()->getKey('id'), $timeslot_ids, 'IN')
      ->condition('field_smart_date.value', $date->end_value, '<')
      ->condition('field_smart_date.end_value', $date->value, '>')
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Checks whether users can be registered on a timeslot.
   *
   * @param \Drupal\Core\Entity\EntityInterface $timeslot
   *   The timeslot.
   * @param \Drupal\Core\Session\AccountInterface[] $users
   *   The users to register, not registered yet.
   * @param \Drupal\Core\Session\AccountInterface $actor
   *   The user registering them.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The reasons why the users cannot be registered, empty when they can.
   */
  public function validateRegistration(EntityInterface $timeslot, array $users, AccountInterface $actor): array {
    $errors = [];
    $bypass = $actor->hasPermission(self::BYPASS_PERMISSION);

    if (!$bypass && $this->isDeadlinePassed($timeslot, 'flag')) {
      $errors[] = $this->t('The registration deadline has passed.');
    }

    $remaining = $bypass ? NULL : $this->getRemainingPlaces($timeslot);
    if ($remaining !== NULL && count($users) > $remaining) {
      $errors[] = $remaining === 0
        ? $this->t('There are no more available spots for this timeslot.')
        : $this->formatPlural($remaining, 'Only 1 spot is left on this timeslot.', 'Only @count spots are left on this timeslot.');
    }

    foreach ($users as $user) {
      if ($overlapping = $this->getOverlappingTimeslot($timeslot, $user)) {
        $errors[] = (int) $user->id() === (int) $actor->id()
          ? $this->t('You are already registered on %timeslot at the same time.', ['%timeslot' => $overlapping->label()])
          : $this->t('%user is already registered on %timeslot at the same time.', [
            '%user' => $user->getDisplayName(),
            '%timeslot' => $overlapping->label(),
          ]);
      }
    }

    return $errors;
  }

  /**
   * Checks whether a user can be unregistered from a timeslot.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The reasons why the user cannot be unregistered, empty when they can.
   */
  public function validateUnregistration(EntityInterface $timeslot, AccountInterface $actor): array {
    if (!$actor->hasPermission(self::BYPASS_PERMISSION) && $this->isDeadlinePassed($timeslot, 'unflag')) {
      return [$this->t('The deadline to unregister has passed.')];
    }
    return [];
  }

}
