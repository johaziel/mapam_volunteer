<?php

namespace Drupal\mapam_volunteer_og;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\Og;
use Drupal\Core\Entity\EntityInterface;

/**
 * VolunteerOgManager class.
 *
 * Manages OG membership of volunteers.
 */
class VolunteerOgManager implements VolunteerOgManagerInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface|null
   */
  protected $membershipManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The OG membership manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MembershipManagerInterface $membership_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipManager = $membership_manager;
  }

  /**
   * Recursive function to get the parent nodes of a given node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $tree
   *   The parent nodes of the current node.
   *
   * @return array
   *   Array of parent nodes, ordered.
   */
  public function getParentNodesTree(EntityInterface $entity, array &$tree = []): array {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle_id = $entity->bundle();
    $is_group_content = Og::isGroupContent($entity_type_id, $bundle_id);

    if (!$is_group_content) {
      return $tree;
    }

    // Get the og_group audience field value of the pole node.
    $group = $this->getParentGroup($entity);

    if ($group) {
      $tree[] = [
        'text' => $group->label(),
        'url' => $group->toUrl(),
      ];
      // Recursively call the function with the parent node.
      $this->getParentNodesTree($group, $tree);
    }

    return $tree;
  }

  /**
   * Return the parent group of the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The child entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false
   *   The parent group, or false if none.
   */
  public function getParentGroup(EntityInterface $entity): EntityInterface|false {
    $groups = $this->membershipManager->getGroups($entity);
    // The groups are returned as a two-dimensional array. Flatten it.
    $groups = array_reduce($groups, 'array_merge', []);
    // Get the first group (if any).
    $group = reset($groups);

    return $group;
  }

  /**
   * Recursively finds the parent group of the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The child entity.
   */
  public function findGrandParentGroup(EntityInterface $entity): ?EntityInterface {
    $groups = $this->membershipManager->getGroups($entity);
    $groups = array_reduce($groups, 'array_merge', []);
    $group = reset($groups);

    if (!$group) {
      return NULL;
    }

    if ($group->bundle() === 'event') {
      return $group;
    }

    return $this->findGrandParentGroup($group);
  }

}
