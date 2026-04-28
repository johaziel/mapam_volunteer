<?php

namespace Drupal\mapam_volunteer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a User Source annotation object.
 *
 * @Annotation
 */
class ParentEventTarget extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A description of the user source.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The priority of this source (higher = more important).
   *
   * @var int
   */
  public $priority = 0;

}
