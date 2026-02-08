<?php

namespace Drupal\mapam_volunteer\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\flag\FlagCountManagerInterface;

/**
 * Adds flag limit comparison.
 *
 * @SearchApiProcessor(
 *   id = "flag_limit_comparison",
 *   label = @Translation("Flag limit comparison"),
 *   description = @Translation("Compares flag count with field limit"),
 *   stages = {
 *     "add_properties" = 0,
 *   }
 * )
 */
class FlagLimitComparison extends ProcessorPluginBase {

  /**
   * The flag count service.
   *
   * @var \Drupal\flag\FlagCountManagerInterface
   */
  protected $flagCount;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->flagCount = $container->get('flag.count');
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      // Bool : limit reached or not.
      $properties['flag_limit_reached'] = new ProcessorProperty([
        'label' => $this->t('Flag limit reached'),
        'description' => $this->t('Whether the flag count has reached the limit'),
        'type' => 'boolean',
        'processor_id' => $this->getPluginId(),
      ]);

      // Integer : actual flag count.
      $properties['flag_count'] = new ProcessorProperty([
        'label' => $this->t('Flag count'),
        'description' => $this->t('Current number of flags'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ]);

      // Integer : remaining places.
      $properties['flag_remaining'] = new ProcessorProperty([
        'label' => $this->t('Flag remaining'),
        'description' => $this->t('Number of flags remaining before limit'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();

      if (!$entity->hasField('field_flag_limit')) {
        return;
      }

      // Get the limit from the entity field.
      $limit = (int) $entity->get('field_flag_limit')->value;

      // Get the current flag count.
      $flag_counts = $this->flagCount->getEntityFlagCounts($entity);
      $flag_id = 'volunteer';
      $current_count = $flag_counts[$flag_id] ?? 0;

      // Compare current count with limit.
      $is_limit_reached = ($current_count >= $limit);
      $remaining = max(0, $limit - $current_count);

      // Add values to the indexed fields.
      $fields = $item->getFields(FALSE);

      // Limit reached.
      $limit_fields = $this->getFieldsHelper()
        ->filterForPropertyPath($fields, NULL, 'flag_limit_reached');
      foreach ($limit_fields as $field) {
        $field->addValue($is_limit_reached);
      }

      // Actual count.
      $count_fields = $this->getFieldsHelper()
        ->filterForPropertyPath($fields, NULL, 'flag_count');
      foreach ($count_fields as $field) {
        $field->addValue($current_count);
      }

      // Remaining places.
      $remaining_fields = $this->getFieldsHelper()
        ->filterForPropertyPath($fields, NULL, 'flag_remaining');
      foreach ($remaining_fields as $field) {
        $field->addValue($remaining);
      }

    }
    catch (\Exception $e) {
      \Drupal::logger('mapam_volunteer')->error('Error in flag limit comparison: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
