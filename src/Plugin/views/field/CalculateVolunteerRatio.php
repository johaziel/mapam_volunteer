<?php

namespace Drupal\mapam_volunteer\Plugin\views\field;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;

/**
 * Provides a custom field for a specific content type.
 *
 * @ingroup views_field_handlers
 * @ViewsField("calculate_volunteer_ratio")
 */
class CalculateVolunteerRatio extends NumericField {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CalculateVolunteerRatio object.
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Prevent Views from adding this field to the SQL query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    $entity = $row->_entity;

    // Get all timeslots for the current entity.
    if ($entity->hasField('og_audience')) {
      // For OG-based implementation, we assume the entity is group content and we need to find the parent group.
      $timeslots = $this->entityTypeManager->getStorage('node')->loadByProperties(
        ['type' => 'timeslot', 'og_audience' => $entity->id(), 'status' => 1]
      );
    }
    else {
      $timeslots = $this->entityTypeManager->getStorage('node')->loadByProperties(
        ['type' => 'timeslot', 'field_pole' => $entity->id(), 'status' => 1]
      );
    }

    $flags = 0;
    $limits = 0;
    foreach ($timeslots as $timeslot) {

      // Get the flag limit for the current timeslot.
      $flag_limit = $timeslot->get('field_flag_limit')->value ?? 0;
      $limits += $flag_limit;

      // Count how many users already flagged timeslot.
      if ($flag_limit) {
        // Count how many users already flagged this timeslot.
        $count = \Drupal::entityQuery('flagging')
          ->accessCheck(FALSE)
          ->condition('flag_id', 'volunteer')
          ->condition('entity_type', $timeslot->getEntityTypeId())
          ->condition('entity_id', $timeslot->id())
          ->count()
          ->execute();

        $flags += $count ?? 0;
      }

    }
    $percentage = 0;

    if ($limits > 0) {
      $percentage = min(100, ($flags / $limits) * 100);
    }

    $percentage = number_format($percentage, 0, $this->options['decimal'], $this->options['separator']);
    // Check to see if hiding should happen before adding prefix and suffix
    // and before rewriting.
    if ($this->options['hide_empty'] && empty($value) && ($value !== 0 || $this->options['empty_zero'])) {
      return '';
    }

    // After the hide_empty check NULL values should be treated as a 0 value.
    $percentage = $percentage ?? 0;

    // Use round first to avoid negative zeros.
    $percentage = round($percentage);
    // Test against both integer zero and float zero.
    if ($this->options['empty_zero'] && ($percentage === 0)) {
      return '';
    }

    return [
      '#attached' => [
        'library' => [
          'mapam_volunteer/bootstrap',
        ],
      ],
      '#type' => 'container',
      '#attributes' => [
        'class' => ['progress'],
        'title' => $this->t('@flags / @limits volunteers', [
          '@flags' => $flags,
          '@limits' => $limits,
        ]),
      ],
      'bar' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['progress-bar'],
          'role' => 'progressbar',
          'style' => "width: {$percentage}%",
          'aria-valuenow' => $percentage,
          'aria-valuemin' => 0,
          'aria-valuemax' => 100,
        ],
        'label' => [
          '#markup' => $percentage . '%',
        ],
      ],
    ];
  }

}
