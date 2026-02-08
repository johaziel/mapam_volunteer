<?php

namespace Drupal\mapam_volunteer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;

/**
 * The Local Task Access Controler.
 */
class LocalTaskAccessController extends ControllerBase {

  /**
   * IsAllowedAccess function.
   */
  public function isAllowedAccess() {
    $bundle = FALSE;
    $route = \Drupal::routeMatch();
    $account = \Drupal::currentUser();
    $routeName = $route->getRouteName();
    $node = $route->getParameter('node');

    if ($node instanceof NodeInterface) {
      $bundle = $node->bundle();
    }
    else {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($route->getParameter('node'));
      if ($node instanceof NodeInterface) {
        $bundle = $node->bundle();
      }
    }
    // The first part ensures the local task (tab) is shown on node pages of type 'event'.
    // The second part makes sure the tab also displays on the view itself.
    // (the view route is view.VIEW_MACHINE_NAME.DISPLAY_MACHINE_NAME)
    return AccessResult::allowedIf(
      $account->hasPermission('administer flaggings') && (
        $bundle === 'event' ||
        ($routeName === 'entity.node.edit_form' && $bundle === 'event') ||
        $routeName === 'view.volunteers_planning.page_1' ||
        $routeName === 'view.manage_volunteer_flag.page_1'
      )
    );
  }

  /**
   * Do not cache.
   *
   * @return int
   *   The maximum cache lifetime in seconds; 0 indicates no caching.
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
