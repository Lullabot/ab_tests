<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Plugin\AbVariantDecider;

use Drupal\ab_tests\AbVariantDeciderPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Plugin implementation of the ab_variant_decider.
 */
abstract class TimeoutAbDeciderBase extends AbVariantDeciderPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'timeout' => ['min' => 0, 'max' => 0],
      'available_variants' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    return [
      'timeout' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Timeout'),
        '#description' => $this->t('The time it takes to decide a variant randomly. The delay is a random number between the minimum and the maximum. This tries to mock an HTTP request to a 3rd party service.'),
        '#description_display' => 'before',
        'min' => [
          '#type' => 'number',
          '#min' => 0,
          '#title' => $this->t('Minimum'),
          '#description' => $this->t('The minimum time that it can take to decide the variant. In milliseconds.'),
          '#default_value' => $configuration['timeout']['min'],
        ],
        'max' => [
          '#type' => 'number',
          '#min' => 0,
          '#title' => $this->t('Maximum'),
          '#description' => $this->t('The maximum time that it can take to decide the variant. In milliseconds.'),
          '#default_value' => $configuration['timeout']['max'],
        ],
      ],
      'available_variants' => $this->timeoutVariantSettingsForm(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $minimum = $form_state->getValue(['timeout', 'min']);
    $maximum = $form_state->getValue(['timeout', 'max']);
    if (!is_numeric($minimum) || $minimum < 0) {
      $form_state->setError($form['timeout']['min'], $this->t('The minimum should be a positive number.'));
    }
    if (!is_numeric($maximum) || $maximum < 0) {
      $form_state->setError($form['timeout']['max'], $this->t('The maximum should be a positive number.'));
    }
    if ($minimum > $maximum) {
      $form_state->setError($form['timeout'], $this->t('The minimum cannot be greater than the maximum.'));
    }
    $available_variants = array_filter($form_state->getValue(['available_variants']));
    if (count($available_variants) < 2) {
      $form_state->setError($form['available_variants'], $this->t('Select, at least, two variants for the A/B test.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getJavaScriptSettings(): array {
    $configuration = $this->getConfiguration();
    $available_variants = array_values(
      array_filter($configuration['available_variants'] ?? [])
    );
    return [
      'availableVariants' => $available_variants,
      'timeout' => $configuration['timeout'] ?? [],
    ];
  }

  /**
   * The form to specify the variants.
   *
   * @return array
   *   The form.
   */
  abstract protected function timeoutVariantSettingsForm(): array;

}
