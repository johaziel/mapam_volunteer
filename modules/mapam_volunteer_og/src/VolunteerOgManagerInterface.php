<?php

namespace Drupal\mapam_volunteer_og;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface VolunteerOgManagerInterface.
 *
 * Manages OG membership of volunteers.
 */
interface VolunteerOgManagerInterface {

  /**
   * Recursive function to get the parent nodes of a given node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to find the parent.
   * @param array $tree
   *   The parent nodes of the current node.
   *
   * @return array
   *   Parent nodes ordered from top-level to immediate parent.
   */
  public function getParentNodesTree(EntityInterface $entity, array &$tree = []): array;

  /**
   * Return the parent group of the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The child entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false
   *   The parent group, or false if none.
   */
  public function getParentGroup(EntityInterface $entity): EntityInterface|false;

  /**
   * Recursively finds the grand parent group of type 'event'.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The child entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The event group, or null if not found.
   */
  public function findGrandParentGroup(EntityInterface $entity): ?EntityInterface;

}
