<?php

namespace Drupal\mapam_volunteer;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Applies the registration rules of volunteers to the flag service.
 */
class MapamVolunteerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('flag')) {
      // The flag service is autowired: the new constructor arguments are
      // resolved from their interfaces and class names.
      $container->getDefinition('flag')->setClass(VolunteerFlagService::class);
    }
  }

}
