<?php

namespace Drupal\mapam_volunteer_og\Plugin\FlagFseSource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flag_fse\Plugin\FlagFseSourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\og\Og;

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
   * The Volunteer OG manager.
   *
   * @var \Drupal\mapam_volunteer_og\VolunteerOgManagerInterface|null
   */
  protected $volunteerOgManager;

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
    if ($container->has('mapam_volunteer_og.volunteer_og_manager')) {
      $instance->volunteerOgManager = $container->get('mapam_volunteer_og.volunteer_og_manager');
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity) {

    if ($entity->bundle() !== 'timeslot') {
      return FALSE;
    }

    // Check if entity is a group or group content.
    return Og::isGroup($entity->getEntityTypeId(), $entity->bundle()) ||
           Og::isGroupContent($entity->getEntityTypeId(), $entity->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableUsers(EntityInterface $entity) {
    $user_ids = [];

    // Recusively find the parent group if the entity is group content.
    $parent_group = $this->volunteerOgManager->findGrandParentGroup($entity);

    // Get group members.
    if ($parent_group) {
      $memberships = $this->membershipManager->getGroupMembershipsByRoleNames($parent_group, ['member']);
      foreach ($memberships as $membership) {
        $user_ids[] = $membership->getOwnerId();
      }
    }

    return $user_ids;
  }

}
