<?php

namespace Drupal\mapam_volunteer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The Local Task Access Controler for Event.
 */
class LocalTaskEventAccessController extends ControllerBase {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current user service.
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
   * Constructs a new LocalTaskEventAccessController object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RouteMatchInterface $route_match, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * IsAllowedAccess function.
   */
  public function isAllowedAccess() {
    $bundle = FALSE;
    $routeName = $this->routeMatch->getRouteName();
    $node = $this->routeMatch->getParameter('node');

    if ($node instanceof NodeInterface) {
      $bundle = $node->bundle();
    }
    else {
      $node = $this->entityTypeManager->getStorage('node')->load($this->routeMatch->getParameter('node'));
      if ($node instanceof NodeInterface) {
        $bundle = $node->bundle();
      }
    }
    // The first part ensures the local task (tab) is shown on node pages of type 'event'.
    // The second part makes sure the tab also displays on the view itself.
    // (the view route is view.VIEW_MACHINE_NAME.DISPLAY_MACHINE_NAME)
    return AccessResult::allowedIf(
      $this->currentUser->hasPermission('administer flaggings') && (
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
