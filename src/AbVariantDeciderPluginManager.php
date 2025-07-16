<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\ab_tests\Annotation\AbVariantDecider as AbVariantDeciderAnnotation;
use Drupal\ab_tests\Attribute\AbVariantDecider as AbVariantDeciderAttribute;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * AbVariantDecider plugin manager.
 */
final class AbVariantDeciderPluginManager extends DefaultPluginManager implements UiPluginManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/AbVariantDecider',
      $namespaces,
      $module_handler,
      AbVariantDeciderInterface::class,
      AbVariantDeciderAttribute::class,
      AbVariantDeciderAnnotation::class,
    );
    $this->alterInfo('ab_variant_decider_info');
    $this->setCacheBackend($cache_backend, 'ab_variant_decider_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugins(?array $plugin_ids = NULL, array $settings = []): array {
    if (is_null($plugin_ids)) {
      $definitions = $this->getDefinitions();
      $plugin_ids = array_map(
        function ($definition) {
          return empty($definition) ? NULL : $definition['id'];
        }, $definitions
      );
      $plugin_ids = array_filter(array_values($plugin_ids));
    }
    $deciders = array_map(
      function ($plugin_id) use ($settings) {
        try {
          return $this->createInstance($plugin_id, $settings[$plugin_id] ?? []);
        }
        catch (PluginException) {
          return NULL;
        }
      }, $plugin_ids
    );
    return array_filter(
      $deciders, function ($decider) {
        return $decider instanceof AbVariantDeciderInterface;
      }
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginTypeLabel(): MarkupInterface {
    return $this->t('Variant Decider');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginTypeSectionName(): MarkupInterface {
    return $this->t('Variants');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginTypeDescription(): MarkupInterface {
    return $this->t('Configure the variants of the A/B tests. A variant decider is responsible for deciding which variant to load. This may be a random variant, using a provider like LaunchDarkly, etc.');
  }

}
