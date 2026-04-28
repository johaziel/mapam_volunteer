<?php

namespace Drupal\mapam_volunteer_og\Plugin\ParentEventTarget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\mapam_volunteer\Plugin\ParentEventTargetBase;

/**
 * Provides default user source (all users).
 *
 * @ParentEventTarget(
 *   id = "og_target",
 *   label = @Translation("Organic Groups parent event target"),
 *   description = @Translation("Event organic group."),
 *   priority = 50
 * )
 */
class OgParentEventTarget extends ParentEventTargetBase {

  /**
   * The OG group type manager.
   *
   * @var \Drupal\og\OgGroupTypeManagerInterface|null
   */
  protected $groupTypeManager;

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface|null
   */
  protected $membershipManager;

  /**
   * The parent group entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $parentGroup = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('og.group_type_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity) {
    $this->groupTypeManager = \Drupal::service('og.group_type_manager');

    return $this->groupTypeManager->isGroup($entity->getEntityTypeId(), $entity->bundle()) ||
           $this->groupTypeManager->isGroupContent($entity->getEntityTypeId(), $entity->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEventTarget(EntityInterface $entity) {
    $this->groupTypeManager = \Drupal::service('og.group_type_manager');
    $this->membershipManager = \Drupal::service('og.membership_manager');

    // Recusively find the parent group if the entity is group content.
    $this->findGrandParentGroup($entity);

    if ($this->parentGroup && $this->parentGroup->bundle() === 'event') {
      return $this->parentGroup;
    }

  }

  /**
   * Recursively finds the parent group of the given entity.
   */
  protected function findGrandParentGroup(EntityInterface $entity) {
    $groups = $this->membershipManager->getGroups($entity);
    // The groups are returned as a two-dimensional array. Flatten it.
    $groups = array_reduce($groups, 'array_merge', []);
    // Get the first group (if any).
    $group = reset($groups);

    if ($group && $group->bundle() === 'event') {
      $this->parentGroup = $group;
      return;
    }
    $this->findGrandParentGroup($group);

  }

}
