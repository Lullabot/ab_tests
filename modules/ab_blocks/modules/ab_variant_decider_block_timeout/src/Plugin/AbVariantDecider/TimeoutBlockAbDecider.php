<?php

declare(strict_types=1);

namespace Drupal\ab_variant_decider_block_timeout\Plugin\AbVariantDecider;

use Drupal\ab_tests\Plugin\AbVariantDecider\TimeoutAbDeciderBase;

/**
 * Plugin implementation of the ab_variant_decider.
 *
 * @AbVariantDecider(
 *   id = "timeout_block",
 *   label = @Translation("Timeout (Block)"),
 *   description = @Translation("A/B variant decider based on a random timeout."),
 *   supported_features = {"ab_blocks"},
 *   decider_library = "ab_tests/ab_variant_decider.timeout",
 * )
 */
class TimeoutBlockAbDecider extends TimeoutAbDeciderBase {

  /**
   * {@inheritdoc}
   */
  protected function timeoutVariantSettingsForm(): array {
    $options = [
      '{"label_display":"0","formatter":{"third_party_settings":{"nomarkup":{"enabled":false}}}}' => $this->t('With field markup, but no title'),
      '{"label_display":"1","formatter":{"third_party_settings":{"nomarkup":{"enabled":true}}}}' => $this->t('Without field markup, but with title'),
    ];
    $configuration = $this->getConfiguration();
    return [
      '#title' => $this->t('Available Variants (Field Formatter)'),
      '#descriptions' => $this->t('After the timeout randomly choose between these bundles of settings. These work best on blocks for fields.'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $configuration['available_variants'],
    ];
  }

}
