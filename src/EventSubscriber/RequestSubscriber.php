<?php

/**
 * @file
 * Contains request event subscriber.
 */

namespace Drupal\mapam_volunteer\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\flag\FlagInterface;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Request event subscriber.
 */
class RequestSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * RequestSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * Handles response event.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onResponse(ResponseEvent $event) {
    $route_name = $this->routeMatch->getRouteName();
    $response = $event->getResponse();

    // Check flag/unflag route with AJAX response.
    if (in_array($route_name, ['flag.action_link_flag', 'flag.action_link_unflag']) && $response instanceof AjaxResponse) {

      if (($flag = $this->routeMatch->getParameter('flag')) instanceof FlagInterface) {
        $user = \Drupal::currentUser();
        $entity_id = $this->routeMatch->getParameter('entity_id');
        $entity_type = $flag->getFlaggableEntityTypeId();
        $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

        $item_start = $entity->get('date')->value;
        $item_end   = $entity->get('date')->end_value;

        $item_date_start = new DrupalDateTime($item_start);
        $item_db_start = $item_date_start->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

        $item_date_end = new DrupalDateTime($item_end);
        $item_db_end = $item_date_end->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

        $connection = \Drupal::database();

        $query = $connection->select('flagging', 'f')
          ->fields('f', ['entity_id'])
          ->condition('f.flag_id', $flag->id())
          ->condition('f.uid', $user->id());
        $query->leftJoin('timeline_schedule_item__date', 'd', 'd.entity_id = f.entity_id');
        $query->condition('d.entity_id', $entity_id, '<>');
        $query->condition('d.date_value', $item_db_end, '<=');
        $query->condition('d.date_end_value', $item_db_start, '>=');

        $conflicts = $query->execute()->fetchCol();

        if (!empty($conflicts)) {
          //$flagging = \Drupal::service('flag')->getFlagging($flag, $entity);
          //$flagging->delete();
          $response->addCommand(new MessageCommand($this->t("You are already registred on another schedule item at the same time."), NULL, ['type' => 'error']));

        }
        else {
          $response->addCommand(new MessageCommand($this->t("You are registred !")));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    return $events;
  }

}
