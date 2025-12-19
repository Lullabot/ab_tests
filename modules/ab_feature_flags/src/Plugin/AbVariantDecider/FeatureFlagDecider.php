<?php

declare(strict_types=1);

namespace Drupal\ab_feature_flags\Plugin\AbVariantDecider;

use Drupal\ab_tests\AbVariantDeciderPluginBase;
use Drupal\ab_tests\Attribute\AbVariantDecider;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feature_flags\AlgorithmConditionPluginManager;
use Drupal\feature_flags\DecisionAlgorithmPluginManager;
use Drupal\feature_flags\Entity\FeatureFlag;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation using feature flags for A/B variant decisions.
 */
#[AbVariantDecider(
  id: 'feature_flag_decider',
  label: new TranslatableMarkup('Feature Flag'),
  description: new TranslatableMarkup('Uses feature flags to determine A/B test variants.'),
  supported_features: ['ab_view_modes', 'ab_blocks'],
  decider_library: 'ab_feature_flags/ab_variant_decider.feature_flag',
)]
final class FeatureFlagDecider extends AbVariantDeciderPluginBase {

  /**
   * Constructs a FeatureFlagDecider object.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads feature flag entities for form options and validation.
   * @param \Drupal\feature_flags\DecisionAlgorithmPluginManager $algorithmPluginManager
   *   Creates algorithm plugin instances for library extraction.
   * @param \Drupal\feature_flags\AlgorithmConditionPluginManager $conditionPluginManager
   *   Creates condition plugin instances for library extraction.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DecisionAlgorithmPluginManager $algorithmPluginManager,
    protected readonly AlgorithmConditionPluginManager $conditionPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.feature_flags.decision_algorithm'),
      $container->get('plugin.manager.feature_flags.algorithm_condition')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'flag_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();

    $feature_flags = $this->entityTypeManager
      ->getStorage('feature_flag')
      ->loadMultiple();

    $options = array_map(
      static fn($flag) => $flag->label(),
      $feature_flags
    );

    $form['flag_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Feature Flag'),
      '#description' => $this->t('Select the feature flag to use for determining variants. The JSON value of the selected variant will be used as the decision value.'),
      '#options' => $options,
      '#default_value' => $configuration['flag_id'],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select a feature flag -'),
    ];
    foreach ($feature_flags as $id => $feature_flag) {
      assert($feature_flag instanceof FeatureFlag);
      $form['flag_id'][$id]['#description'] = $feature_flag->getDescription();
    }

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p><strong>Important:</strong> For view mode testing, the feature flag variant value should be a JSON string (e.g., <code>"teaser"</code>). For block testing, it should be a JSON object with block configuration (e.g., <code>{"label_display": "0"}</code>).</p>'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $flag_id = $form_state->getValue('flag_id');

    if (empty($flag_id)) {
      $form_state->setError($form['flag_id'], $this->t('Please select a feature flag.'));
      return;
    }

    // Prevent runtime errors from deleted flags in saved configuration.
    $flag = $this->loadFeatureFlag($flag_id);

    if (!$flag) {
      $form_state->setError($form['flag_id'], $this->t('The selected feature flag does not exist.'));
      return;
    }

    // A/B testing requires comparing at least two variants.
    $variants = $flag->getVariants();
    if (count($variants) < 2) {
      $form_state->setError(
        $form['flag_id'],
        $this->t('The selected feature flag must have at least 2 variants for A/B testing.')
      );
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function getLibraryDependencies(): array {
    $libraries = parent::getLibraryDependencies();
    $flag_id = $this->getConfiguration()['flag_id'] ?? NULL;
    if (!$flag_id) {
      return $libraries;
    }
    $flag = $this->loadFeatureFlag($flag_id);
    if (!$flag) {
      return $libraries;
    }

    $algorithm_libraries = array_reduce(
      $flag->getAlgorithms(),
      fn(array $acc, array $algorithm) => [
        ...$acc,
        ...$this->extractAlgorithmLibraries($algorithm),
      ],
      []
    );

    // Accumulated libraries: decider, algorithms, and conditions.
    return array_values(array_unique([...$libraries, ...$algorithm_libraries]));
  }

  /**
   * Extracts libraries from an algorithm and its conditions.
   *
   * @param array $algorithm
   *   The algorithm configuration from the feature flag.
   *
   * @return array
   *   The libraries required by this algorithm and its conditions.
   */
  private function extractAlgorithmLibraries(array $algorithm): array {
    $plugin_id = $algorithm['plugin_id'] ?? NULL;
    if (!$plugin_id) {
      return [];
    }

    try {
      $definition = $this->algorithmPluginManager->getDefinition($plugin_id);
      $algorithm_library = !empty($definition['js_library']) ? [$definition['js_library']] : [];

      // Conditions may have their own JS implementations.
      $condition_libraries = array_reduce(
        $algorithm['conditions'] ?? [],
        fn(array $acc, array $condition) => [
          ...$acc,
          ...$this->extractConditionLibrary($condition),
        ],
        []
      );

      return [...$algorithm_library, ...$condition_libraries];
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * Extracts the library from a condition.
   *
   * @param array $condition
   *   The condition configuration from an algorithm.
   *
   * @return array
   *   The library required by this condition, or empty array if none.
   */
  private function extractConditionLibrary(array $condition): array {
    $plugin_id = $condition['plugin_id'] ?? NULL;
    if (!$plugin_id) {
      return [];
    }

    try {
      $definition = $this->conditionPluginManager->getDefinition($plugin_id);
      return !empty($definition['js_library']) ? [$definition['js_library']] : [];
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $flag_id = $this->configuration['flag_id'] ?? NULL;
    if (!$flag_id) {
      return $dependencies;
    }

    // Warn admins before deleting flags used in active A/B tests.
    $flag = $this->loadFeatureFlag($flag_id);
    if (!$flag) {
      return $dependencies;
    }

    $dependencies['config'][] = $flag->getConfigDependencyName();
    return $dependencies;
  }

  /**
   * Loads a feature flag entity by ID.
   *
   * @param string $flag_id
   *   The feature flag machine name.
   *
   * @return \Drupal\feature_flags\Entity\FeatureFlag|null
   *   The loaded flag, or NULL if not found.
   */
  private function loadFeatureFlag(string $flag_id): ?object {
    return $this->entityTypeManager
      ->getStorage('feature_flag')
      ->load($flag_id);
  }

}
