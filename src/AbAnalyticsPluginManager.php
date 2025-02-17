<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\ab_tests\Annotation\AbAnalytics;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * AbAnalytics plugin manager.
 */
final class AbAnalyticsPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AbAnalytics', $namespaces, $module_handler, AbAnalyticsInterface::class, AbAnalytics::class);
    $this->alterInfo('ab_analytics_info');
    $this->setCacheBackend($cache_backend, 'ab_analytics_plugins');
  }

  /**
   * Gets all analytics provider plugins.
   *
   * @param array|null $plugin_ids
   *   The IDs to load.
   * @param array $settings
   *   The settings for the providers keyed by the plugin ID.
   *
   * @return \Drupal\ab_tests\AbAnalyticsInterface[]
   *   The plugin instances.
   */
  public function getAnalytics(?array $plugin_ids = NULL, array $settings = []): array {
    if (is_null($plugin_ids)) {
      $definitions = $this->getDefinitions();
      $plugin_ids = array_map(function ($definition) {
        return empty($definition) ? NULL : $definition['id'];
      }, $definitions);
      $plugin_ids = array_filter(array_values($plugin_ids));
    }
    $providers = array_map(function ($plugin_id) use ($settings) {
      try {
        return $this->createInstance($plugin_id, $settings[$plugin_id] ?? []);
      }
      catch (PluginException) {
        return NULL;
      }
    }, $plugin_ids);
    return array_filter($providers);
  }

}
