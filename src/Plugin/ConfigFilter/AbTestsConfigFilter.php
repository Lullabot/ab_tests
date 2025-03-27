<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Plugin\ConfigFilter;

use Drupal\config_filter\Plugin\ConfigFilterBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters out AB Tests settings during config export.
 *
 * @ConfigFilter(
 *   id = "ab_tests_config_filter",
 *   label = "AB Tests Config Filter",
 *   weight = 0
 * )
 */
class AbTestsConfigFilter extends ConfigFilterBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AbTestsConfigFilter.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function filterWrite($name, array $data) {
    $config = $this->configFactory->get('ab_tests.settings');
    if (!$config->get('ignore_config_export')) {
      return $data;
    }

    // Only filter node type configuration.
    if (!str_starts_with($name, 'node.type.')) {
      return $data;
    }
    // Remove AB Tests third-party settings.
    if (isset($data['third_party_settings']['ab_tests'])) {
      unset($data['third_party_settings']['ab_tests']);

      // If there are no more third-party settings, remove the array.
      if (empty($data['third_party_settings'])) {
        unset($data['third_party_settings']);
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterRead($name, $data) {
    $config = $this->configFactory->get('ab_tests.settings');
    if (!$config->get('ignore_config_export')) {
      return $data;
    }

    // Only filter node type configuration.
    if (!str_starts_with($name, 'node.type.')) {
      return $data;
    }
    // Load the current configuration and copy the A/B Tests settings to it.
    $config = $this->configFactory->get($name);
    $ab_tests_settings = $config->get('third_party_settings.ab_tests');
    if (!empty($ab_tests_settings)) {
      $data['third_party_settings']['ab_tests'] = $ab_tests_settings;
    }
    return $data;
  }

}
