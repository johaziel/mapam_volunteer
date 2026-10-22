<?php

namespace Drupal\mapam_volunteer;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Builds the "Home > Event > Pole > Timeslot" breadcrumb.
 *
 * Applies to the pages and dashboards of events, poles and timeslots. Poles
 * are shared by the editions of the festival: their event is the current one.
 */
class MapamVolunteerBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The routes of the node pages.
   */
  protected const ROUTES = [
    'entity.node.canonical',
    'entity.node.edit_form',
    'mapam_volunteer.node.dashboard',
    'mapam_volunteer.node.view',
  ];

  /**
   * The node types of the festival.
   */
  protected const BUNDLES = ['event', 'pole', 'timeslot', 'timeslot_recur'];

  public function __construct(
    protected CurrentEvent $currentEvent,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    // @todo Remove null safe operator in Drupal 12.0.0, see
    //   https://www.drupal.org/project/drupal/issues/3459277.
    $cacheable_metadata?->addCacheContexts(['route']);
    $node = $route_match->getParameter('node');
    return in_array($route_match->getRouteName(), self::ROUTES, TRUE)
      && $node instanceof NodeInterface
      && in_array($node->bundle(), self::BUNDLES, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    // @todo Remove in Drupal 12.0.0, will be added from ::applies(). See
    //   https://www.drupal.org/project/drupal/issues/3459277
    $breadcrumb->addCacheContexts(['route']);
    $breadcrumb->addCacheTags([CurrentEvent::CACHE_TAG]);
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $trail = [];
    if ($node->bundle() !== 'event' && ($event = $this->currentEvent->getForNode($node))) {
      $trail[] = $event;
    }
    if ($node->hasField('field_pole') && ($pole = $node->get('field_pole')->entity)) {
      $trail[] = $pole;
    }
    foreach ($trail as $parent) {
      $breadcrumb->addLink($parent->toLink());
      $breadcrumb->addCacheableDependency($parent);
    }

    // The current page, as plain text like the other breadcrumbs of the site.
    $breadcrumb->addLink(Link::fromTextAndUrl($node->label(), Url::fromRoute('<none>')));
    $breadcrumb->addCacheableDependency($node);
    return $breadcrumb;
  }

}
