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
 * Controller for node workspace view.
 */
class WorkspaceController extends ControllerBase {

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
   * Constructs a WorkspaceController.
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
   * Displays the node in workspace view mode.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   A render array.
   */
  public function nodeWorkspace(NodeInterface $node) {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Build the node using the 'workspace' view mode.
    $build = $view_builder->view($node, 'workspace');
    $build['#attached']['library'][] = 'mapam_volunteer/workspace';
    return $build;
  }

  /**
   * Provides the title for the node dashboard page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The page title.
   */
  public function nodeWorkspaceTitle(NodeInterface $node) {
    return $this->t('@title', ['@title' => $node->label()]);
  }

  /**
   * Checks access to the node workspace.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function nodeWorkspaceAccess(NodeInterface $node): AccessResultInterface {
    // Define allowed content types.
    $allowed_types = ['organization', 'pole'];

    // Check if the node type is in the allowed list.
    if (!in_array($node->bundle(), $allowed_types)) {
      return AccessResult::forbidden()
        ->addCacheableDependency($node)
        ->addCacheTags(['node_type:' . $node->bundle()]);
    }

    if ($this->currentUser->hasPermission('access node workspace')) {
      return AccessResult::allowed()
        ->cachePerUser()
        ->addCacheableDependency($node);
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($node)
      ->addCacheTags(['node_type:' . $node->bundle()]);
  }

}
