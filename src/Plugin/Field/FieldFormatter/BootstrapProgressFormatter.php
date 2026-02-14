<?php

namespace Drupal\mapam_volunteer\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Plugin implementation of the 'bootstrap_progress' formatter.
 *
 * @FieldFormatter(
 *   id = "bootstrap_progress",
 *   label = @Translation("Bootstrap progress bar"),
 *   field_types = {
 *     "integer",
 *     "decimal"
 *   }
 * )
 */
class BootstrapProgressFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $value = (int) $item->value;
      $max = 100;

      $percent = min(100, max(0, ($value / $max) * 100));

      $elements[$delta] = [
        '#type' => 'markup',
        '#markup' => '
          <div class="progress">
            <div class="progress-bar" role="progressbar"
              style="width: ' . $percent . '%"
              aria-valuenow="' . $value . '"
              aria-valuemin="0"
              aria-valuemax="' . $max . '">
              ' . $value . '
            </div>
          </div>',
      ];
    }

    return $elements;
  }

}
