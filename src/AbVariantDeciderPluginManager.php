<?php declare(strict_types = 1);

namespace Drupal\ab_tests;

use Drupal\ab_tests\Annotation\AbVariantDecider;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * AbVariantDecider plugin manager.
 */
final class AbVariantDeciderPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AbVariantDecider', $namespaces, $module_handler, AbVariantDeciderInterface::class, AbVariantDecider::class);
    $this->alterInfo('ab_variant_decider_info');
    $this->setCacheBackend($cache_backend, 'ab_variant_decider_plugins');
  }

  /**
   * Instantiates all the variant decider plugins.
   *
   * @return \Drupal\ab_tests\AbVariantDeciderInterface[]
   *   The plugin instances.
   */
  public function getDeciders($plugin_ids = NULL): array {
    if (!$plugin_ids) {
      $definitions = $this->getDefinitions();
      $plugin_ids = array_map(function ($definition) {
        return empty($definition) ? NULL : $definition['id'];
      }, $definitions);
      $plugin_ids = array_filter(array_values($plugin_ids));
    }
    $deciders = array_map(function ($plugin_id) {
      try {
        return $this->createInstance($plugin_id);
      }
      catch (PluginException) {
        return NULL;
      }
    }, $plugin_ids);
    return array_filter($deciders, function ($decider) {
      return $decider instanceof AbVariantDeciderInterface;
    });
  }

}
