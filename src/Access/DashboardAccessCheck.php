<?php

namespace Drupal\mapam_volunteer\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Access check for the dashboard route.
 */
class DashboardAccessCheck implements AccessInterface {

  /**
   * Checks access to the node dashboard.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessNode(AccountInterface $account, NodeInterface $node) {
    // Define allowed content types.
    $allowed_types = ['event', 'pole', 'timeslot'];
    ddl($node->bundle());
    // Check if the node type is in the allowed list.
    if (!in_array($node->bundle(), $allowed_types)) {
      return AccessResult::forbidden()
        ->addCacheableDependency($node)
        ->addCacheTags(['node_type:' . $node->bundle()]);
    }

    if ($account->hasPermission('access node dashboard')) {
      return AccessResult::allowed()
        ->cachePerUser()
        ->addCacheableDependency($node);
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($node)
      ->addCacheTags(['node_type:' . $node->bundle()]);
  }

  /**
   * Checks access to the user dashboard.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessUser(AccountInterface $account, UserInterface $user) {

    if ($account->hasPermission('access user dashboard')) {
      return AccessResult::allowed()
        ->cachePerUser()
        ->addCacheableDependency($user);
    }

    return AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheableDependency($user);
  }

}
