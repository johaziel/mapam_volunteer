<?php

namespace Drupal\mapam_volunteer\Plugin\views\field;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a custom field for a specific content type.
 *
 * @ingroup views_field_handlers
 * @ViewsField("count_flags_limit_by_pole")
 */
class CountFlagsLimitByPole extends NumericField {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PriceDefaultFormatter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Prevent Views from adding this field to the SQL query.
    // $this->addAdditionalFields([]);.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    $entity = $row->_entity;

    // Get all timeslots for the current entity.
    $timeslots = $this->entityTypeManager->getStorage('node')->loadByProperties(
      ['type' => 'timeslot', 'field_pole' => $entity->id(), 'status' => 1]
    );

    $value = 0;
    foreach ($timeslots as $timeslot) {
      // Get the flag linit for the current timeslot.
      $flag_limit = $timeslot->get('field_flag_limit')->value ?? 0;
      $value += $flag_limit;
    }

    // Check to see if hiding should happen before adding prefix and suffix
    // and before rewriting.
    if ($this->options['hide_empty'] && empty($value) && ($value !== 0 || $this->options['empty_zero'])) {
      return '';
    }

    // After the hide_empty check NULL values should be treated as a 0 value.
    $value = $value ?? 0;
    if (!empty($this->options['set_precision'])) {
      $precision = $this->options['precision'];
    }
    elseif ($decimal_position = strpos($value, '.')) {
      $precision = strlen($value) - $decimal_position - 1;
    }
    else {
      $precision = 0;
    }

    // Use round first to avoid negative zeros.
    $value = round($value, $precision);
    // Test against both integer zero and float zero.
    if ($this->options['empty_zero'] && ($value === 0 || $value === 0.0)) {
      return '';
    }

    $value = number_format($value, $precision, $this->options['decimal'], $this->options['separator']);

    // If we should format as plural, take the (possibly) translated plural
    // setting and format with the current language.
    if (!empty($this->options['format_plural'])) {
      $value = PluralTranslatableMarkup::createFromTranslatedString($value, $this->options['format_plural_string']);
    }
    return $this->sanitizeValue($this->options['prefix'], 'xss')

      . $this->sanitizeValue($value)
      . $this->sanitizeValue($this->options['suffix'], 'xss');
  }

}
