<?php

namespace Drupal\mapam_volunteer;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Finds out whether a user is a referent of the poles of a node.
 *
 * Referents are the users of the "field_primary_contacts" field of a pole.
 * They manage the pole, its timeslots and see the dashboards of the events
 * the pole belongs to.
 */
class PoleReferents {

  /**
   * The permission to manage all poles, regardless of their referents.
   */
  public const BYPASS_PERMISSION = 'manage all poles';

  /**
   * Gets the poles of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A pole, a timeslot or an event.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The pole itself, the pole of a timeslot or the poles of an event.
   */
  public function getPoles(NodeInterface $node): array {
    $field_name = match ($node->bundle()) {
      'pole' => NULL,
      'timeslot', 'timeslot_recur' => 'field_pole',
      'event' => 'field_event_poles',
      default => FALSE,
    };
    if ($field_name === NULL) {
      return [$node];
    }
    if ($field_name === FALSE || !$node->hasField($field_name)) {
      return [];
    }
    return $node->get($field_name)->referencedEntities();
  }

  /**
   * Checks whether a user manages a pole, or one of the poles of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A pole, a timeslot or an event.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Allowed for referents and users with the bypass permission, neutral
   *   otherwise.
   */
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    if ($account->hasPermission(self::BYPASS_PERMISSION)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $result = AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($node);
    foreach ($this->getPoles($node) as $pole) {
      $result->addCacheableDependency($pole);
      if ($this->isPoleReferent($pole, $account)) {
        return AccessResult::allowed()->inheritCacheability($result);
      }
    }
    return $result;
  }

  /**
   * Checks whether a user is a referent of a pole.
   */
  public function isPoleReferent(NodeInterface $pole, AccountInterface $account): bool {
    if (!$pole->hasField('field_primary_contacts')) {
      return FALSE;
    }
    foreach ($pole->get('field_primary_contacts') as $item) {
      if ((int) $item->target_id === (int) $account->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
