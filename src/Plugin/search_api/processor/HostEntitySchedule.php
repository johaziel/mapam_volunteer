<?php

namespace Drupal\mapam_volunteer\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mapam_volunteer\Plugin\search_api\processor\Property\EntityProcessorProperty;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\timeline_schedule\Entity\Schedule;
use Drupal\search_api\Item\Field;

/**
 * Adds the user's soul mate node for indexing.
 *
 * @SearchApiProcessor(
 *   id = "host_entity_schedule",
 *   label = @Translation("Host Entity Schedule"),
 *   description = @Translation("The host entity associated with the schedule."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class HostEntitySchedule extends ProcessorPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface|null
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    $processor->setFieldsHelper($container->get('search_api.fields_helper'));

    return $processor;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Retrieves the fields helper.
   *
   * @return \Drupal\search_api\Utility\FieldsHelperInterface
   *   The fields helper.
   */
  public function getFieldsHelper() {
    return $this->fieldsHelper ?: \Drupal::service('search_api.fields_helper');
  }

  /**
   * Sets the fields helper.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fields_helper
   *   The new fields helper.
   *
   * @return $this
   */
  public function setFieldsHelper(FieldsHelperInterface $fields_helper) {
    $this->fieldsHelper = $fields_helper;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'timeline_schedule') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'timeline_schedule') {
      $definition = [
        'label' => $this->t('Schedule host entity'),
        'description' => $this->t("The host entity associated with the schedule."),
        'type' => 'entity:timeline_schedule',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['host_entity_schedule'] = new EntityProcessorProperty($definition);
      $properties['host_entity_schedule']->setEntityTypeId('timeline_schedule');
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    //ddl('addfield');
    $schedule = $item->getOriginalObject()->getValue();
    if (!($schedule instanceof Schedule)) {
      return;
    }
    
    /** @var \Drupal\search_api\Item\FieldInterface[][] $to_extract */
    $to_extract = [];

    $entity_type_id = $schedule->getHostEntityTypeId();
    $entity_id = $schedule->getHostEntityId();

    ddl($item->getFields());
    foreach ($item->getFields() as $field) {
      $datasource = $field->getDatasource();
      $property_path = $field->getPropertyPath();
      list($direct, $nested) = Utility::splitPropertyPath($property_path, FALSE);
      if ($datasource
          && $datasource->getEntityTypeId() === 'timeline_schedule'
          && $direct === 'host_entity_schedule') {
        $to_extract[$nested][] = $field;
      }
    }
    ddl($to_extract);

    if (!$to_extract) {
      return;
    }

    $entity = $this->getEntityTypeManager()
      ->getStorage($entity_type_id)
      ->load($entity_id);
    if (!$entity) {
      return;
    }

    // This is a pretty hack-y work-around to make property extraction work for
    // Views fields, too. In general, adding entities as field values is a
    // pretty bad idea, so this might blow up in some use cases. If not
    // required, the foreach block should thus be commented out.
    if (isset($to_extract[''])) {
      foreach ($to_extract[''] as $field) {
        $field->setValues([$entity]);
      }
      unset($to_extract['']);
    }

    $field_id = $to_extract['id'][0] ?? null;
    if($field_id) {
      $field_id->addValue($entity_id);
    }

    $field_entity_id = $to_extract['entity_id'][0] ?? null;
    if($field_entity_id) {
      $field_entity_id->addValue($entity_type_id);
    }
  }

}
