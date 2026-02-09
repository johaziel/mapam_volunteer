<?php

namespace Drupal\mapam_volunteer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for node dashboard view.
 */
class NodeDashboardController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a NodeDashboardController.
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
  public function view(NodeInterface $node) {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Build the node using the 'dashboard' view mode.
    $build = $view_builder->view($node, 'dashboard');

    return $build;
  }

  /**
   * Provides the title for the dashboard page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The page title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Dashboard: @title', ['@title' => $node->label()]);
  }

}
