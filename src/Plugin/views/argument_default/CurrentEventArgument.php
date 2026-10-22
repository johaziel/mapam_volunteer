<?php

namespace Drupal\mapam_volunteer\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mapam_volunteer\CurrentEvent;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument: the festival event of the page, or the current event.
 *
 * Use it on a contextual filter on the event ID, e.g. the "field_event" of
 * timeslots, so that a view shows the edition of the page it is displayed on,
 * and the current edition elsewhere.
 */
#[ViewsArgumentDefault(
  id: 'mapam_volunteer_current_event',
  title: new TranslatableMarkup('Festival event of the page, or the current event'),
)]
class CurrentEventArgument extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The current event service.
   *
   * @var \Drupal\mapam_volunteer\CurrentEvent
   */
  protected $currentEvent;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->currentEvent = $container->get('mapam_volunteer.current_event');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $event = $this->currentEvent->getForRoute();
    return $event ? (string) $event->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['route'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [CurrentEvent::CACHE_TAG];
  }

}
