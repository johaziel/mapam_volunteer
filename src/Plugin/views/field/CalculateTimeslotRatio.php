<?php

namespace Drupal\mapam_volunteer\Plugin\views\field;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Drupal\flag\Entity\Flag;

/**
 * Calculate Timeslot Ratio.
 *
 * @ingroup views_field_handlers
 * @ViewsField("calculate_timeslot_ratio")
 */
class CalculateTimeslotRatio extends NumericField {

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

    $flag = Flag::load('volunteer');
    $flaggings = \Drupal::entityTypeManager()
      ->getStorage('flagging')->loadByProperties([
        'flag_id' => $flag->id(),
        'entity_type' => 'node',
        'entity_id' => $entity->id(),
      ]);
    $count = count($flaggings);
    $capacity = $entity->hasField('field_flag_limit') ? $entity->get('field_flag_limit')->value : 0;

    $percentage = $capacity > 0 ? round(($count / $capacity * 100), 0) : 0;
    // Ensure occupancy does not exceed 100%.
    $percentage = min($percentage, 100);
    if ($capacity > 0) {
      return [
        '#attached' => [
          'library' => [
            'mapam_volunteer/bootstrap_progress',
          ],
        ],
        '#type' => 'container',
        '#attributes' => [
          'class' => ['progress'],
          'title' => $this->t('@flags / @limits volunteers', [
            '@flags' => $count,
            '@limits' => $capacity,
          ]),
        ],
        'bar' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['progress-bar', 'bg-info', 'text-white'],
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

}
