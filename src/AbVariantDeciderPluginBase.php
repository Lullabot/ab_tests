<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for ab_variant_decider plugins.
 */
abstract class AbVariantDeciderPluginBase extends PluginBase implements AbVariantDeciderInterface, DependentPluginInterface, ContainerFactoryPluginInterface, ConfigurableInterface, PluginFormInterface {

  use StringTranslationTrait;

  /**
   * The library that will decide the variant using JS.
   *
   * @var string
   */
  protected string $variantDeciderLibrary = 'ab_test/ab_variant_decider_error';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): MarkupInterface {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#attached' => [
        'library' => [$this->pluginDefinition['decider_library'] ?? 'ab_test/ab_variant_decider_null'],
      ],
    ];
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

}
