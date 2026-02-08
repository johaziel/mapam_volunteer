<?php

namespace Drupal\mapam_volunteer\Plugin\views\access;

use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Local Task Access.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "mapam_volunteer.access_handler",
 *   title = @Translation("Volunteer Local Task Access"),
 * )
 */
class LocalTaskAccess extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Volunteer Local Task Access');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    // If we have made it here, I am OK with proceeding.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_custom_access', '\Drupal\mapam_volunteer\Controller\LocalTaskAccessController:isAllowedAccess');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
