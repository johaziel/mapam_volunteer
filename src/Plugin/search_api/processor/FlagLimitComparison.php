<?php

namespace Drupal\mapam_volunteer\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\flag_search_api\Plugin\search_api\processor\FlagIndexer;
use Psr\Log\LoggerInterface;
use Drupal\flag\FlagService;

/**
 * Adds flag limit comparison.
 *
 * @SearchApiProcessor(
 *   id = "flag_limit_comparison",
 *   label = @Translation("Flag limit comparison"),
 *   description = @Translation("Compares flag count with field limit"),
 *   stages = {
 *     "add_properties" = 1,
 *     "pre_index_save" = -10
 *   }
 * )
 */
class FlagLimitComparison extends FlagIndexer {

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flagService;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag.count'),
      $container->get('flag'),
      $container->get('logger.factory')->get('flag_search_api')

    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $flag_count, FlagService $flagService, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $flagService, $logger);
    $this->flagCount = $flag_count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['flag_index']['#description'] = $this->t('Index overall limit of users who flagged this content.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMyOldPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
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

      // Integer : percentage of remaining places.
      $properties['flag_percentage'] = new ProcessorProperty([
        'label' => $this->t('Flag percentage'),
        'description' => $this->t('Percentage of flags remaining before limit'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * Helper function for defining our custom fields.
   */
  protected function getFieldsDefinition() {
    $config = $this->configuration['flag_index'];
    $fields = [];
    foreach ($config as $flag) {
      $label = $this->flagService->getFlagById($flag)->get('label');
      $fields['flag_' . $flag . '_limit_count'] = [
        'label' => $this->t('@label limit count', ['@label' => $label]),
        'description' => $label,
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];

      $fields['flag_' . $flag . '_limit_reached'] = [
        'label' => $this->t('@label limit reached', ['@label' => $label]),
        'description' => $this->t('Whether the flag count has reached the limit'),
        'type' => 'boolean',
        'processor_id' => $this->getPluginId(),
      ];

      // Integer : actual flag count.
      $fields['flag_' . $flag . '_limit_count'] = [
        'label' => $this->t('@label limit count', ['@label' => $label]),
        'description' => $this->t('Current number of flags'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];

      // Integer : remaining places.
      $fields['flag_' . $flag . '_limit_remaining'] = [
        'label' => $this->t('@label limit remaining', ['@label' => $label]),
        'description' => $this->t('Number of flags remaining before limit'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];

      // Integer : percentage of remaining places.
      $fields['flag_' . $flag . '_limit_percentage'] = [
        'label' => $this->t('@label limit percentage', ['@label' => $label]),
        'description' => $this->t('Percentage of flags remaining before limit'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $config = $this->configuration['flag_index'];
    try {
      $entity = $item->getOriginalObject()->getValue();

      if (!$entity->hasField('field_flag_limit')) {
        return;
      }
      // Get the limit from the entity field.
      $limit = (int) $entity->get('field_flag_limit')->value;

      foreach ($config as $flag_id) {
        // Get the current flag count.
        $flag_counts = $this->flagCount->getEntityFlagCounts($entity);
        $current_count = $flag_counts[$flag_id] ?? 0;
        // Compare current count with limit.
        $is_limit_reached = ($current_count >= $limit);
        $remaining = max(0, $limit - $current_count);

        // Add values to the indexed fields.
        $fields = $item->getFields(FALSE);

        // Limit reached.
        $limit_fields = $this->getFieldsHelper()
          ->filterForPropertyPath($fields, NULL, 'flag_' . $flag_id . '_limit_reached');
        foreach ($limit_fields as $field) {
          $field->addValue($is_limit_reached);
        }

        // Actual count.
        $count_fields = $this->getFieldsHelper()
          ->filterForPropertyPath($fields, NULL, 'flag_' . $flag_id . '_limit_count');
        foreach ($count_fields as $field) {
          $field->addValue($current_count);
        }

        // Remaining places.
        $remaining_fields = $this->getFieldsHelper()
          ->filterForPropertyPath($fields, NULL, 'flag_' . $flag_id . '_limit_remaining');
        foreach ($remaining_fields as $field) {
          $field->addValue($remaining);
        }

        // Percentage of remaining places.
        $percentage_fields = $this->getFieldsHelper()
          ->filterForPropertyPath($fields, NULL, 'flag_' . $flag_id . '_limit_percentage');
        foreach ($percentage_fields as $field) {
          // Calculate percentage.
          $percent = $limit > 0 ? round(($current_count / $limit * 100), 0) : 0;
          // Ensure percent does not exceed 100%.
          $percent = min($percent, 100);
          $field->addValue($percent);

        }

      }

    }
    catch (SearchApiException $exception) {
      $this->logger->error($exception->getMessage());
    }
  }

}
