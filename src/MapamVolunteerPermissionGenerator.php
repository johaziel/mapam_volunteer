<?php

namespace Drupal\mapam_volunteer;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Timeline Schedule Permission Generator.
 *
 * @package Drupal\timeline_schedule
 */
class MapamVolunteerPermissionGenerator implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    TranslationInterface $translation,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * Return an array of per-entity flag permissions.
   *
   * @return array
   *   An array of permissions
   */
  public function permissions() {
    $permissions = [];

    $flags = \Drupal::service('flag')->getAllFlags();
    // Charger tous les flags déclarés.
    foreach ($flags as $flag_id => $flag) {
      $label = $flag->label();

      $permissions["view others flaggings $flag_id"] = [
        'title' => $this->t('View other users flaggings of "@label"', ['@label' => $label]),
        'description' => $this->t('Allows users to see flaggings of other users for the "@label" flag.', ['@label' => $label]),
      ];
    }
    return $permissions;

  }

}
