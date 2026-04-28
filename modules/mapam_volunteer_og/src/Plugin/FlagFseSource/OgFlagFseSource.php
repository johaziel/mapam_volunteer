<?php

namespace Drupal\mapam_volunteer_og\Plugin\FlagFseSource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flag_fse\Plugin\FlagFseSourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Organic Groups user source.
 *
 * @FlagFseSource(
 *   id = "organic_groups",
 *   label = @Translation("Organic Groups members"),
 *   description = @Translation("Only group members are available for selection."),
 *   priority = 10
 * )
 */
class OgFlagFseSource extends FlagFseSourceBase implements ContainerFactoryPluginInterface {

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
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    if ($container->has('og.group_type_manager')) {
      $instance->groupTypeManager = $container->get('og.group_type_manager');
    }
    if ($container->has('og.membership_manager')) {
      $instance->membershipManager = $container->get('og.membership_manager');
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity) {
    $this->groupTypeManager = \Drupal::service('og.group_type_manager');

    if ($entity->bundle() !== 'timeslot') {
      return FALSE;
    }

    // Check if entity is a group or group content.
    return $this->groupTypeManager->isGroup($entity->getEntityTypeId(), $entity->bundle()) ||
           $this->groupTypeManager->isGroupContent($entity->getEntityTypeId(), $entity->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableUsers(EntityInterface $entity) {
    $user_ids = [];

    // Recusively find the parent group if the entity is group content.
    $this->findGrandParentGroup($entity);

    // Get group members.
    if ($this->parentGroup) {
      $memberships = $this->membershipManager->getGroupMembershipsByRoleNames($this->parentGroup, ['member']);
      foreach ($memberships as $membership) {
        $user_ids[] = $membership->getOwnerId();
      }
    }

    return $user_ids;
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
