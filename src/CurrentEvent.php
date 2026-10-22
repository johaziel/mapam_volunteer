<?php

namespace Drupal\mapam_volunteer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;

/**
 * Finds the festival event of a page.
 *
 * Pages of an event or of a timeslot belong to that event. Other pages,
 * including poles which are shared by the editions, belong to the current
 * event: the one with "field_current_event" checked.
 */
class CurrentEvent {

  /**
   * The field marking the current event.
   */
  public const FIELD = 'field_current_event';

  /**
   * The cache tag invalidated when an event changes.
   */
  public const CACHE_TAG = 'node_list:event';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Gets the current event.
   */
  public function getCurrent(): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'event')
      ->condition(self::FIELD, 1)
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Gets the event of a node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The event itself, the event of a timeslot, or the current event for
   *   other nodes.
   */
  public function getForNode(NodeInterface $node): ?NodeInterface {
    if ($node->bundle() === 'event') {
      return $node;
    }
    if ($node->hasField('field_event') && ($event = $node->get('field_event')->entity)) {
      return $event;
    }
    return $this->getCurrent();
  }

  /**
   * Gets the event of the current page.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The event of the node of the route, or the current event.
   */
  public function getForRoute(?RouteMatchInterface $route_match = NULL): ?NodeInterface {
    $node = ($route_match ?? $this->routeMatch)->getParameter('node');
    // Views paths like "node/%node/timeline" pass the raw ID.
    if (is_numeric($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }
    return $node instanceof NodeInterface ? $this->getForNode($node) : $this->getCurrent();
  }

  /**
   * Makes an event the only current one.
   */
  public function setCurrent(NodeInterface $event): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'event')
      ->condition(self::FIELD, 1)
      ->condition('nid', $event->id(), '<>')
      ->execute();
    foreach ($storage->loadMultiple($ids) as $other) {
      $other->set(self::FIELD, 0)->save();
    }
  }

}
