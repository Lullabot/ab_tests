<?php

namespace Drupal\ab_tests\Form;

use Drupal\ab_tests\AbAnalyticsPluginManager;
use Drupal\ab_tests\AbVariantDeciderPluginManager;
use Drupal\ab_tests\UiPluginInterface;
use Drupal\ab_tests\UiPluginManagerInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides functionality for plugin selection forms.
 */
trait PluginSelectionFormTrait {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * AJAX callback for the variant/analytics decider selector.
   */
  public function pluginSelectionAjaxCallback(array &$form, FormStateInterface $form_state): array {
    $element = $form_state->getTriggeringElement();
    $parents = [
      ...array_slice($element['#array_parents'], 0, -2),
      'config_wrapper',
    ];
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Gets the plugin manager.
   *
   * @param string $id_key
   *   The ID key.
   *
   * @return \Drupal\ab_tests\UiPluginManagerInterface|null
   *   The plugin manager.
   */
  public function getPluginManager(string $id_key): ?UiPluginManagerInterface {
    try {
      $plugin_manager = match ($id_key) {
        'variants' => $this->variantDeciderManager(),
        'analytics' => $this->analyticsManager(),
        default => NULL,
      };
    }
    catch (PluginException $e) {
      $plugin_manager = NULL;
    }
    return $plugin_manager;
  }

  /**
   * Injects a plugin selector into the form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $settings
   *   The settings array.
   * @param string $plugin_type
   *   The plugin type (e.g., 'variants', 'analytics').
   * @param string $feature
   *   The feature we are injecting this into.
   * @param bool $multiple_cardinality
   *   TRUE for multiple cardinality.
   */
  private function injectPluginSelector(
    array &$form,
    FormStateInterface $form_state,
    array $settings,
    string $plugin_type,
    string $feature,
    bool $multiple_cardinality = FALSE,
  ): void {
    $get_plugin_label = static function (PluginInspectionInterface $plugin): string {
      return $plugin instanceof UiPluginInterface ? $plugin->label() : $plugin->getPluginId();
    };

    $plugin_manager = $this->getPluginManager($plugin_type);

    if (!$plugin_manager) {
      return;
    }
    $form['ab_tests'][$plugin_type] = [
      '#type' => 'fieldset',
      '#title' => $plugin_manager->getPluginTypeSectionName(),
      '#description' => $plugin_manager->getPluginTypeDescription(),
      '#description_display' => 'before',
      '#states' => [
        'visible' => [
          ':input[name="ab_tests[is_active]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $wrapper_id = sprintf('%s-config-wrapper', $plugin_type);

    // Get the currently selected plugins, either from form_state or settings.
    $selected_plugin_ids = $this->getSelectedPluginsFromFormState($form_state, $plugin_type, $multiple_cardinality)
      ?: $this->getSelectedPluginsFromSettings($settings);

    $settings = $multiple_cardinality
      ? $settings
      : array_map(
        static fn (string $selected_plugin_id) => $settings,
        array_combine($selected_plugin_ids, $selected_plugin_ids),
      );
    // List all plugins with settings for selected ones.
    $plugin_settings = array_reduce(
      $selected_plugin_ids,
      static fn(array $plugin_settings, string $plugin_id) => [
        ...$plugin_settings,
        $plugin_id => $settings[$plugin_id]['settings'] ?? [],
      ],
      []
    );
    $plugins = $plugin_manager->getPlugins(settings: $plugin_settings);
    // Filter the plugins by their supported features.
    $plugins = array_filter(
      $plugins,
      static function (PluginInspectionInterface $plugin) use ($feature) {
        $supported_features = $plugin->getPluginDefinition()['supported_features'] ?? [];
        return empty($supported_features) || in_array($feature, $supported_features);
      },
    );

    // Add the checkboxes with AJAX callback.
    $form['ab_tests'][$plugin_type]['id'] = [
      '#type' => $multiple_cardinality ? 'checkboxes' : 'radios',
      '#title' => $plugin_manager->getPluginTypeLabel(),
      '#options' => array_combine(
        array_map(static fn(PluginInspectionInterface $plugin) => $plugin->getPluginId(), $plugins),
        array_map($get_plugin_label, $plugins),
      ),
      '#default_value' => $multiple_cardinality ? $selected_plugin_ids : reset($selected_plugin_ids),
      '#ajax' => [
        'callback' => [$this, 'pluginSelectionAjaxCallback'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];

    // Add a wrapper div for the plugin configuration form.
    $form['ab_tests'][$plugin_type]['config_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
      ],
    ];

    foreach ($selected_plugin_ids as $plugin_id) {
      $selected_plugins = array_filter(
        $plugins,
        static fn(PluginInspectionInterface $plugin) => $plugin->getPluginId() === $plugin_id
      );
      $selected_plugin = reset($selected_plugins);

      if (!$selected_plugin instanceof PluginFormInterface) {
        continue;
      }
      $form['ab_tests'][$plugin_type]['config_wrapper'][$plugin_id . '_settings'] = $this->buildPluginConfigurationForm(
        $selected_plugin,
        $form_state,
        $this->t('- No configuration options available for this plugin -'),
      );
    }
  }

  /**
   * Gets the variant decider plugin manager.
   *
   * @return \Drupal\ab_tests\AbVariantDeciderPluginManager
   *   The variant decider plugin manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function variantDeciderManager(): AbVariantDeciderPluginManager {
    if (isset($this->variantDeciderManager)
      && $this->variantDeciderManager instanceof AbVariantDeciderPluginManager
    ) {
      return $this->variantDeciderManager;
    }
    return \Drupal::service('plugin.manager.ab_variant_decider');
  }

  /**
   * Gets the analytics plugin manager.
   *
   * @return \Drupal\ab_tests\AbAnalyticsPluginManager
   *   The analytics plugin manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function analyticsManager(): AbAnalyticsPluginManager {
    if (isset($this->analyticsManager)
      && $this->analyticsManager instanceof AbAnalyticsPluginManager
    ) {
      return $this->analyticsManager;
    }
    return \Drupal::service('plugin.manager.ab_analytics');
  }

  /**
   * Gets the selected plugins from the form state for analytics plugins.
   *
   * This method retrieves the selected plugin IDs from the form state, either
   * from the triggering element during AJAX requests or from the submitted
   * form values. It filters out empty values and returns only valid plugin IDs.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state containing the submitted values.
   * @param string $plugin_type
   *   The plugin type (e.g., 'analytics').
   * @param bool $multiple_cardinality
   *   TRUE if we are dealing with multiple cardinality.
   *
   * @return array
   *   Array of selected plugin IDs, with empty values filtered out.
   */
  protected function getSelectedPluginsFromFormState(FormStateInterface $form_state, string $plugin_type, bool $multiple_cardinality): array {
    $triggering_element = $form_state->getTriggeringElement();

    // If this is an AJAX request, and the triggering element is the plugin
    // selector.
    if ($triggering_element && isset($triggering_element['#parents']) && end($triggering_element['#parents']) === 'id') {
      $plugin_values = $form_state->getValue([
        'ab_tests',
        $plugin_type,
        'id',
      ]);
      return $multiple_cardinality
        ? array_filter($plugin_values ?? [])
        : [$plugin_values];
    }

    // Try to get from values if the form has been submitted.
    if ($form_state->hasValue(['ab_tests', $plugin_type, 'id'])) {
      $plugin_values = $form_state->getValue(['ab_tests', $plugin_type, 'id']);
      return $multiple_cardinality
        ? array_filter($plugin_values ?? [])
        : [$plugin_values];
    }

    return [];
  }

  /**
   * Gets the selected plugins from settings for analytics plugins.
   *
   * @param array $settings
   *   The settings array.
   *
   * @return array
   *   Array of selected plugin IDs.
   */
  protected function getSelectedPluginsFromSettings(array $settings): array {
    // Handle legacy single plugin format.
    if (isset($settings['id'])) {
      return [$settings['id']];
    }

    return array_filter(
      array_map(
        static fn ($plugin_config) => $plugin_config['id'] ?? NULL,
        $settings,
      )
    );
  }

  /**
   * Builds a plugin configuration form.
   *
   * @param \Drupal\Core\Plugin\PluginFormInterface $plugin
   *   The plugin.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $empty_message
   *   The message to display when there are no configuration options.
   *
   * @return array
   *   The plugin configuration form.
   */
  protected function buildPluginConfigurationForm(PluginFormInterface $plugin, FormStateInterface $form_state, string $empty_message): array {
    if (!$plugin instanceof PluginInspectionInterface) {
      return [];
    }
    $form = [
      '#type' => 'fieldset',
      '#title' => $plugin->getPluginDefinition()['label'],
      '#description' => $plugin->getPluginDefinition()['description'] ?? '',
      '#description_display' => 'before',
    ];

    // Build an empty array for the subform.
    $element = [];

    // Create a proper SubformState that won't contain closures.
    $subform_state = SubformState::createForSubform($element, $form, $form_state);

    // Build the configuration form.
    $settings_form = $plugin->buildConfigurationForm($element, $subform_state);

    if (empty($settings_form)) {
      $form['#markup'] = $empty_message;
    }
    else {
      $form += $settings_form;
    }

    return $form;
  }

  /**
   * Validates the plugin form.
   *
   * This method ensures that only the selected plugin's form is validated.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $plugin_type_id
   *   The plugin type ID.
   * @param bool $multiple_cardinality
   *   TRUE for multiple cardinality.
   */
  protected function validatePluginForm(array &$form, FormStateInterface $form_state, string $plugin_type_id, bool $multiple_cardinality = FALSE): void {
    // Skip validation if A/B tests are not active.
    if (!$form_state->getValue(['ab_tests', 'is_active'])) {
      return;
    }

    $plugin_manager = $this->getPluginManager($plugin_type_id);

    if (!$plugin_manager) {
      return;
    }

    $selected_plugin_ids = $this->getSelectedPluginsFromFormState($form_state, $plugin_type_id, $multiple_cardinality);

    foreach ($selected_plugin_ids as $plugin_id) {
      if (!$plugin_id) {
        continue;
      }

      // Check if the form element exists before proceeding.
      if (!isset($form['ab_tests'][$plugin_type_id]['config_wrapper'][$plugin_id . '_settings'])) {
        continue;
      }

      // Set validation limits before creating any plugin instances to avoid
      // serialization issues.
      $form_state->setLimitValidationErrors(
        [
          [
            'ab_tests',
            $plugin_type_id,
            'config_wrapper',
            $plugin_id . '_settings',
          ],
        ]
      );

      // Get plugin settings.
      $plugin_settings = $form_state->getValue([
        'ab_tests',
        $plugin_type_id,
        'config_wrapper',
        $plugin_id . '_settings',
      ]) ?? [];
      $plugin = $plugin_manager->createInstance($plugin_id, $plugin_settings);

      if (!$plugin instanceof PluginFormInterface) {
        continue;
      }

      // Create a clean subform element to avoid serialization issues.
      $element = $form['ab_tests'][$plugin_type_id]['config_wrapper'][$plugin_id . '_settings'];
      $subform_state = SubformState::createForSubform($element, $form, $form_state);

      $plugin->validateConfigurationForm(
        $element,
        $subform_state
      );
    }
  }

  /**
   * Updates the plugin configuration in the settings array.
   *
   * This helper method updates the ab_tests settings with plugin configuration.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $plugin_type_id
   *   The plugin type ID (e.g., 'variants', 'analytics').
   * @param bool $multiple_cardinality
   *   TRUE for multiple cardinality.
   *
   * @return array
   *   The updated settings array.
   */
  protected function updatePluginConfiguration(array $form, FormStateInterface $form_state, string $plugin_type_id, bool $multiple_cardinality): array {
    $settings = [];

    $selected_plugin_ids = $this->getSelectedPluginsFromFormState($form_state, $plugin_type_id, $multiple_cardinality);

    foreach ($selected_plugin_ids as $plugin_id) {
      if (!$plugin_id) {
        continue;
      }

      // Check if the plugin has a configuration form.
      if (!isset($form['ab_tests'][$plugin_type_id]['config_wrapper'][$plugin_id . '_settings'])) {
        // Store plugin without configuration.
        $settings[$plugin_type_id][$plugin_id]['id'] = $plugin_id;
        continue;
      }

      // Get the plugin instance.
      $plugin_manager = $this->getPluginManager($plugin_type_id);

      if (!$plugin_manager) {
        continue;
      }

      // Get values from the form.
      $plugin_values = $form_state->getValue([
        'ab_tests',
        $plugin_type_id,
        'config_wrapper',
        $plugin_id . '_settings',
      ]) ?? [];

      // Create instance with merged configuration.
      try {
        $plugin = $plugin_manager->createInstance($plugin_id, $plugin_values);
      }
      catch (PluginException $e) {
        continue;
      }

      // Only process with PluginFormInterface.
      if (!$plugin instanceof PluginFormInterface) {
        $settings[$plugin_type_id][$plugin_id]['id'] = $plugin_id;
        continue;
      }

      // Create subform state and submit form.
      $element = $form['ab_tests'][$plugin_type_id]['config_wrapper'][$plugin_id . '_settings'];
      $subform_state = SubformState::createForSubform($element, $form, $form_state);
      $plugin->submitConfigurationForm($element, $subform_state);

      // Store plugin ID and configuration.
      $settings[$plugin_type_id][$plugin_id]['id'] = $plugin_id;
      $settings[$plugin_type_id][$plugin_id]['settings'] = $plugin->getConfiguration();
    }
    if (!$multiple_cardinality) {
      $settings[$plugin_type_id] = $settings[$plugin_type_id][$plugin_id] ?? [];
    }

    return $settings;
  }

}
