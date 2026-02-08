<?php

namespace Drupal\mapam_volunteer\EventSubscriber;

use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\views\Entity\View;

/**
 * Event subscriber to alter entity print CSS.
 *
 * @see \Drupal\entity_print\Asset\AssetCollector::getCssLibraries
 */
class EntityPrintSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PrintEvents::CSS_ALTER => 'alterCss',
    ];
  }

  /**
   * React to a config object being saved.
   *
   * @param \Drupal\entity_print\Event\PrintCssAlterEvent $event
   *   Entity Print CSS alter event.
   */
  public function alterCss(PrintCssAlterEvent $event) {

    $entities = $event->getEntities();
    $entity = reset($entities);
    if ($entity instanceof View && $entity->id() == 'planning_table') {
      $event->getBuild()['#attached']['library'][] = 'mapam_volunteer/export-planning.css';
    }

  }

}
