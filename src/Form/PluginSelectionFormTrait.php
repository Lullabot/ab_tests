<?php

namespace Drupal\ab_tests\Form;

use Drupal\ab_tests\AbAnalyticsPluginManager;
use Drupal\ab_tests\AbVariantDeciderPluginManager;
use Drupal\ab_tests\UiPluginInterface;
use Drupal\ab_tests\UiPluginManagerInterface;
use Drupal\Component\Annotation\Plugin;
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
   */
  private function injectPluginSelector(
    array &$form,
    FormStateInterface $form_state,
    array $settings,
    string $plugin_type,
  ): void {
    $get_plugin_label = static function(PluginInspectionInterface $plugin): string {
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
    // Get currently selected plugin, either from form_state or settings.
    $selected_plugin_id = $this->getSelectedPluginFromFormState($form_state, $plugin_type)
      ?? ($settings['id'] ?? 'null');

    // List all plugins.
    $plugins = $plugin_manager->getPlugins(
      settings: [$selected_plugin_id => $settings['settings'] ?? []],
    );

    $wrapper_id = sprintf('%s-config-wrapper', $plugin_type);
    // Add the radio buttons with AJAX callback.
    $form['ab_tests'][$plugin_type]['id'] = [
      '#type' => 'radios',
      '#title' => $plugin_manager->getPluginTypeLabel(),
      '#options' => array_combine(
        array_map(static fn(PluginInspectionInterface $plugin) => $plugin->getPluginId(), $plugins),
        array_map($get_plugin_label, $plugins),
      ),
      '#default_value' => $selected_plugin_id,
      '#required' => TRUE,
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

    // Only build the selected plugin's configuration form.
    if (!empty($selected_plugin_id) && $selected_plugin_id !== 'null') {
      $selected_plugins = array_filter(
        $plugins,
        static fn (PluginInspectionInterface $plugin) => $plugin->getPluginId() === $selected_plugin_id
      );
      $selected_plugin = reset($selected_plugins);

      if ($selected_plugin instanceof PluginFormInterface) {
        $form['ab_tests'][$plugin_type]['config_wrapper']['settings'] = $this->buildPluginConfigurationForm(
          $selected_plugin,
          $form_state,
          $this->t('- No configuration options available for this plugin -'),
        );
      }
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
   * Gets the selected plugin from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $plugin_type
   *   The plugin type (e.g., 'variants', 'analytics').
   *
   * @return string|null
   *   The selected plugin ID, or NULL if not found.
   */
  protected function getSelectedPluginFromFormState(FormStateInterface $form_state, string $plugin_type): ?string {
    $triggering_element = $form_state->getTriggeringElement();

    // If this is an AJAX request and the triggering element is the plugin selector.
    if ($triggering_element && isset($triggering_element['#parents']) && end($triggering_element['#parents']) === 'settings') {
      $plugin_id = $form_state->getValue(array_merge([
        'ab_tests',
        $plugin_type,
        'settings',
      ]));
      return $plugin_id;
    }

    // Try to get from values if the form has been submitted.
    if ($form_state->hasValue(['ab_tests', $plugin_type, 'settings'])) {
      return $form_state->getValue(['ab_tests', $plugin_type, 'settings']);
    }

    return NULL;
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

    // Build configuration form.
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
   * @param string $id_key
   *   The ID key.
   */
  protected function validatePluginForm(array &$form, FormStateInterface $form_state, string $plugin_type_id, string $id_key) {
    // Skip validation if A/B tests are not active.
    if (!$form_state->getValue(['ab_tests', 'is_active'])) {
      return;
    }

    $plugin_manager = $this->getPluginManager($plugin_type_id);

    if (!$plugin_manager) {
      return;
    }

    $plugin_id = $form_state->getValue(['ab_tests', $plugin_type_id, 'id']);
    if (!$plugin_id) {
      return;
    }

    // Check if the form element exists before proceeding.
    if (!isset($form['ab_tests'][$plugin_type_id]['config_wrapper']['settings'])) {
      return;
    }

    // Set validation limits before creating any plugin instances to avoid serialization issues.
    // @todo: do we actually need this???
    $form_state->setLimitValidationErrors(
      [
        ['ab_tests', $plugin_type_id, 'config_wrapper', 'settings'],
      ]
    );

    // Get plugin settings.
    $plugin_settings = $form_state->getValue([
      'ab_tests',
      $plugin_type_id,
      'config_wrapper',
      'settings',
    ]) ?? [];
    $plugin = $plugin_manager->createInstance($plugin_id, $plugin_settings);

    if (!$plugin instanceof PluginFormInterface) {
      return;
    }

    // Create a clean subform element to avoid serialization issues.
    $element = $form['ab_tests'][$plugin_type_id]['config_wrapper']['settings'];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);

    $plugin->validateConfigurationForm(
      $element,
      $subform_state
    );
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
   *
   * @return array
   *   The updated settings array.
   */
  protected function updatePluginConfiguration(array $form, FormStateInterface $form_state, string $plugin_type_id): array {
    $settings = [];
    $plugin_id = $form_state->getValue(['ab_tests', $plugin_type_id, 'id']);
    if (!$plugin_id) {
      return $settings;
    }
    $settings[$plugin_type_id]['id'] = $plugin_id;

    // Check if the plugin has a configuration form.
    if (!isset($form['ab_tests'][$plugin_type_id]['config_wrapper']['settings'])) {
      return $settings;
    }

    // Get the plugin instance.
    $plugin_manager = $this->getPluginManager($plugin_type_id);

    if (!$plugin_manager) {
      return $settings;
    }

    // Get values from the form.
    $plugin_values = $form_state->getValue([
      'ab_tests',
      $plugin_type_id,
      'config_wrapper',
      'settings',
    ]) ?? [];

    // Create instance with merged configuration.
    try {
      $plugin = $plugin_manager->createInstance($plugin_id, $plugin_values);
    }
    catch (PluginException $e) {
      return $settings;
    }

    // Only process with PluginFormInterface.
    if (!$plugin instanceof PluginFormInterface) {
      return $settings;
    }
    // Create subform state and submit form.
    $element = $form['ab_tests'][$plugin_type_id]['config_wrapper']['settings'];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    $plugin->submitConfigurationForm($element, $subform_state);

    // Store plugin ID and configuration.
    $settings[$plugin_type_id]['settings'] = $plugin->getConfiguration();

    return $settings;
  }

}
