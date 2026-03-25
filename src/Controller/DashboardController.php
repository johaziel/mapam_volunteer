<?php

namespace Drupal\mapam_volunteer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
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
   * Constructs a DashboardController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the node in dashboard view mode.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   A render array.
   */
  public function nodeDashboard(NodeInterface $node) {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Build the node using the 'dashboard' view mode.
    $build = $view_builder->view($node, 'dashboard');
    $build['#attached']['library'][] = 'mapam_volunteer/dasboard';
    return $build;
  }

  /**
   * Displays the user in dashboard view mode.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   A render array.
   */
  public function userDashboard(UserInterface $user) {
    $view_builder = $this->entityTypeManager->getViewBuilder('user');

    // Build the node using the 'dashboard' view mode.
    $build = $view_builder->view($user, 'dashboard');
    $build['#attached']['library'][] = 'mapam_volunteer/dasboard';
    return $build;
  }

  /**
   * Displays the node in full view mode.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   A render array.
   */
  public function nodeView(NodeInterface $node) {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Build the node using the 'dashboard' view mode.
    $build = $view_builder->view($node, 'full');

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
  public function nodeTitle(NodeInterface $node) {
    return $this->t('@title', ['@title' => $node->label()]);
  }

  /**
   * Provides the title for the user dashboard page.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return string
   *   The page title.
   */
  public function userTitle(UserInterface $user) {
    return $this->t('@title', ['@title' => $user->label()]);
  }

}
