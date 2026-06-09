<?php

namespace Drupal\mapam_volunteer_og\Plugin\ParentEventTarget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\mapam_volunteer\Plugin\ParentEventTargetBase;
use Drupal\og\Og;

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

    $instance->groupTypeManager = $container->get('og.group_type_manager');
    $instance->membershipManager = $container->get('og.membership_manager');
    $instance->volunteerOgManager = $container->get('mapam_volunteer_og.volunteer_og_manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity) {

    return Og::isGroup($entity->getEntityTypeId(), $entity->bundle()) ||
           Og::isGroupContent($entity->getEntityTypeId(), $entity->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEventTarget(EntityInterface $entity) {

    // Recusively find the parent group if the entity is group content.
    $parent_group = $this->volounteerOgManager->findGrandParentGroup($entity);

    return $parent_group;

  }

}
