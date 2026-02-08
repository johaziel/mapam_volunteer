<?php

namespace Drupal\mapam_volunteer\Plugin\views\field;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flag\FlagCountManagerInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a custom field for a specific content type.
 *
 * @ingroup views_field_handlers
 * @ViewsField("entity_flag_count")
 */
class EntityFlagCount extends NumericField {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The flag count.
   *
   * @var \Drupal\flag\FlagCountManagerInterface
   */
  protected $flagCount;

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
   * @param \Drupal\flag\FlagCountManagerInterface $flag_count
   *   The flag count.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, FlagCountManagerInterface $flag_count) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->flagCount = $flag_count;
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
      $container->get('flag.count')
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
    $flag_counts = $this->flagCount->getEntityFlagCounts($entity);

    // Count how many users already flagged this entity.
    $count = \Drupal::entityQuery('flagging')
      ->accessCheck(FALSE)
      ->condition('flag_id', 'volunteer')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->count()
      ->execute();

    $value = $count ?? 0;
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
