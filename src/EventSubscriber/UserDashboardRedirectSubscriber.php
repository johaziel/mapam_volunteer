<?php

namespace Drupal\mapam_volunteer\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to redirect editors from node view to dashboard.
 */
class UserDashboardRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs an EditorRedirectSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    RouteMatchInterface $route_match,
    RequestStack $request_stack,
  ) {
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 28];
    return $events;
  }

  /**
   * Redirects editors to the dashboard view.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event) {

    // Only handle main requests.
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    // Ignor AJAX, preview, and REST requests and node/add.
    if ($request->isXmlHttpRequest()
      || $request->query->get('preview')
      || strpos($request->getPathInfo(), '/jsonapi/') === 0
      || strpos($request->getPathInfo(), '/user/add') === 0) {
      return;
    }

    // Only redirect on the canonical node view route.
    if ($this->routeMatch->getRouteName() !== 'entity.user.canonical') {
      return;
    }

    // Check if user has the 'access node dashboard' permission.
    if (!$this->currentUser->hasPermission('access user dashboard')) {
      return;
    }

    // Get the user from the route.
    $user = $this->routeMatch->getParameter('user');

    if (!$user instanceof UserInterface) {
      return;
    }

    // Build the redirect URL.
    $url = Url::fromRoute('mapam_volunteer.user.dashboard', [
      'user' => $user->id(),
    ]);

    // Create a cacheable redirect response.
    $response = new CacheableRedirectResponse($url->toString());

    // Add cache metadata.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheContexts(['user.roles', 'url.path']);
    $cache_metadata->addCacheTags($user->getCacheTags());
    $response->addCacheableDependency($cache_metadata);

    $event->setResponse($response);
  }

}
