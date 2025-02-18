<?php

namespace Drupal\ab_analytics_tracker_example\Plugin\AbAnalytics;

use Drupal\ab_tests\AbAnalyticsPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * MockTracker analytics provider.
 *
 * @AbAnalytics(
 *   id = "mock_tracker",
 *   label = @Translation("Mock Tracker"),
 *   description = @Translation("Example analytics provider for testing"),
 *   analytics_library = "ab_analytics_tracker_example/ab_analytics_tracker.mock"
 * )
 */
class MockTracker extends AbAnalyticsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => '',
      'tracking_domain' => 'track.mocktracker.local',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    return [
      'api_key' => [
        '#type' => 'textfield',
        '#title' => $this->t('API Key'),
        '#default_value' => $configuration['api_key'],
        '#required' => TRUE,
      ],

      'tracking_domain' => [
        '#type' => 'textfield',
        '#title' => $this->t('Tracking Domain'),
        '#default_value' => $configuration['tracking_domain'],
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Add validation if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['api_key'] = $form_state->getValue('api_key');
    $this->configuration['tracking_domain'] = $form_state->getValue('tracking_domain');
  }

  /**
   * {@inheritdoc}
   */
  protected function getJavaScriptSettings(): array {
    return [
      'apiKey' => $this->configuration['api_key'],
      'trackingDomain' => $this->configuration['tracking_domain'],
    ];
  }

}
