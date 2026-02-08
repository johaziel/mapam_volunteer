<?php

namespace Drupal\volunteer_fse\Plugin\FlagFseSource;

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
class OrganicGroupsFlagFseSource extends FlagFseSourceBase implements ContainerFactoryPluginInterface {

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
    if (!$this->groupTypeManager || !$this->membershipManager) {
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
    $group = NULL;

    // Check if entity is a group.
    if ($this->groupTypeManager->isGroup($entity->getEntityTypeId(), $entity->bundle())) {
      $group = $entity;
    }
    // Check if entity is group content.
    elseif ($this->groupTypeManager->isGroupContent($entity->getEntityTypeId(), $entity->bundle())) {
      $groups = $this->membershipManager->getGroups($entity);
      if (!empty($groups)) {
        foreach ($groups as $entities) {
          $group = reset($entities);
          break;
        }
      }
    }

    // Get group members.
    if ($group) {
      $memberships = $this->membershipManager->getGroupMembershipsByRoleNames($group, ['member']);
      foreach ($memberships as $membership) {
        $user_ids[] = $membership->getOwnerId();
      }
    }

    return $user_ids;
  }

}
