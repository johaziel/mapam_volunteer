<?php

namespace Drupal\mapam_volunteer_og\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for node dashboard view.
 */
class DashboardController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a DashboardController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Displays the node in dashboard view mode.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \Drupal\node\NodeInterface $event
   *   The event entity.
   *
   * @return array
   *   A render array.
   */
  public function eventPoleDashboard(NodeInterface $node, NodeInterface $event) {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Store the context in the private tempstore.
    $tempstore = \Drupal::service('tempstore.private')->get('vis_timeline');
    $tempstore->set('context', [
      'event_id' => $event->id(),
      'pole_id' => $node->id(),
      'event' => $event,
      'pole' => $node,
    ]);

    // Build the node using the 'dashboard' view mode.
    $build = $view_builder->view($node, 'dashboard');
    $build['#attached']['library'][] = 'mapam_volunteer/dashboard';
    return $build;
  }

  /**
   * Provides the title for the node dashboard page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The event entity.
   * @param \Drupal\node\NodeInterface $event
   *   The pole entity.
   *
   * @return string
   *   The page title.
   */
  public function eventPoleTitle(NodeInterface $node, NodeInterface $event) {
    return $this->t('@event : @title', ['@event' => $event->label(), '@title' => $node->label()]);
  }

  /**
   * Checks access to the node dashboard.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \Drupal\node\NodeInterface $event
   *   The event entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function eventPoleAccess(NodeInterface $node, ?NodeInterface $event = NULL): AccessResultInterface {
    // Define allowed content types.
    $allowed_types = ['pole'];

    // Check if the node type is in the allowed list.
    if (!in_array($node->bundle(), $allowed_types)) {
      return AccessResult::forbidden()
        ->addCacheableDependency($node)
        ->addCacheTags(['node_type:' . $node->bundle()]);
    }

    if ($this->currentUser->hasPermission('access node dashboard')) {
      return AccessResult::allowed()
        ->cachePerUser()
        ->addCacheableDependency($node);
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($node)
      ->addCacheTags(['node_type:' . $node->bundle()]);
  }

}
