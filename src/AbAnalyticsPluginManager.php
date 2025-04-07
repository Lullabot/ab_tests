<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\ab_tests\Annotation\AbAnalytics;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * AbAnalytics plugin manager.
 */
final class AbAnalyticsPluginManager extends DefaultPluginManager implements UiPluginManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AbAnalytics', $namespaces, $module_handler, AbAnalyticsInterface::class, AbAnalytics::class);
    $this->alterInfo('ab_analytics_info');
    $this->setCacheBackend($cache_backend, 'ab_analytics_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugins(?array $plugin_ids = NULL, array $settings = []): array {
    if (is_null($plugin_ids)) {
      $definitions = $this->getDefinitions();
      $plugin_ids = array_map(function($definition) {
        return empty($definition) ? NULL : $definition['id'];
      }, $definitions);
      $plugin_ids = array_filter(array_values($plugin_ids));
    }
    $providers = array_map(function($plugin_id) use ($settings) {
      try {
        return $this->createInstance($plugin_id, $settings[$plugin_id] ?? []);
      }
      catch (PluginException) {
        return NULL;
      }
    }, $plugin_ids);
    return array_filter($providers);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginTypeLabel(): MarkupInterface {
    return $this->t('Analytics Tracker');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginTypeSectionName(): MarkupInterface {
    return $this->t('Analytics');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginTypeDescription(): MarkupInterface {
    return $this->t('Configure the trackers for the A/B tests. An analytics tracker is responsible for recording success / failure for the A/B test. This may send data to Google Analytics, etc.');
  }

}
