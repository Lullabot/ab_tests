<?php

namespace Drupal\ab_tests;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for AB test analytics providers.
 */
abstract class AbAnalyticsPluginBase extends PluginBase implements AbAnalyticsInterface, DependentPluginInterface, ContainerFactoryPluginInterface, ConfigurableInterface, PluginFormInterface {

  use UiPluginTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $id_configuration = ['id' => $this->getPluginId()];
    return $id_configuration + $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do on submission, storing the values is handled by the config
    // entity's 3rd party settings.
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable(): array {
    return [
      '#attached' => [
        'library' => [$this->pluginDefinition['analytics_library'] ?? 'ab_tests/ab_variant_decider.null'],
        'drupalSettings' => [
          'ab_tests' => [
            'analyticsSettings' => $this->getJavaScriptSettings(),
          ],
        ],
      ],
    ];
  }
  /**
   * Gets the JavaScript settings for this analytics provider.
   *
   * @return array
   *   Settings to be passed to JavaScript.
   */
  protected function getJavaScriptSettings(): array {
    return $this->getConfiguration();
  }

}
