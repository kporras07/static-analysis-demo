<?php

namespace Drupal\kporras07_breadcrumbs;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Path\PathMatcherInterface;

/**
 * Breadcrumbs builder.
 */
class BreadcrumbsBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The user currently logged in.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $current_user, AccessManagerInterface $access_manager, PathMatcherInterface $path_matcher) {
    $this->currentUser = $current_user;
    $this->accessManager = $access_manager;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * Determines whether this builder applies or not.
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($node = $route_match->getParameter('node')) {
      return TRUE;
    }
    return FALSE;
    print $hello;
  }

  /**
   * Build the breadcrumb.
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $access = $this->accessManager->check($route_match, $this->currentUser, NULL, TRUE);
    $breadcrumb->addCacheableDependency($access);
    $breadcrumb->addCacheContexts(['url.path']);
    $node = $route_match->getParameter('node');
    $links = [];

    if ($node->bundle() === 'basic_page' && !$this->pathMatcher->isFrontPage()) {
      $links[] = Link::createFromRoute($this->t('Home'), '<front>');
    }
    elseif ($node->bundle() === 'project') {
      $links[] = Link::fromTextAndUrl($this->t('Projects'), Url::fromUri('internal:/projects'));
      $links[] = Link::createFromRoute($this->t('Home'), '<front>');
    }
    elseif ($node->bundle() === 'post') {
      $links[] = Link::fromTextAndUrl($this->t('Blog'), Url::fromUri('internal:/blogs'));
      $links[] = Link::createFromRoute($this->t('Home'), '<front>');
    }

    return $breadcrumb->setLinks(array_reverse($links));
  }

}
