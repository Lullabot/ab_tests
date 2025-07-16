<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for A/B Tests.
 */
final class AbTestsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ab_tests.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ab_tests_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ab_tests.settings');
    $default_ignore_config_export = $config->get('ignore_config_export') ?? FALSE;
    $default_debug_mode = $config->get('debug_mode') ?? FALSE;

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add debug messages to the console?'),
      '#description' => $this->t('When enabled, the different JS plugins will add debug messages to the browser console.'),
      '#default_value' => $default_debug_mode,
      '#config_target' => 'ab_tests.settings:debug_mode',
    ];
    $form['ignore_config_export'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore A/B Tests configuration during configuration export'),
      '#description' => $this->t('When enabled, third-party settings for A/B Tests will not be included in the configuration export.'),
      '#default_value' => $default_ignore_config_export,
      '#config_target' => 'ab_tests.settings:ignore_config_export',
    ];

    return parent::buildForm($form, $form_state);
  }

}
