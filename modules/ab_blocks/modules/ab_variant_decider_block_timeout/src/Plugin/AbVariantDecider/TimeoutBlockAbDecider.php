<?php

declare(strict_types=1);

namespace Drupal\ab_variant_decider_block_timeout\Plugin\AbVariantDecider;

use Drupal\ab_tests\Plugin\AbVariantDecider\TimeoutAbDeciderBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
      '{formatter":{"third_party_settings":{"nomarkup":{"enabled":false}},"label":"above"}}' => $this->t('With field markup, label above'),
      '{formatter":{"third_party_settings":{"nomarkup":{"enabled":false}},"label":"below"}}' => $this->t('With field markup, label below'),
      '{formatter":{"third_party_settings":{"nomarkup":{"enabled":false}},"label":"hidden"}}' => $this->t('With field markup, label hidden'),
      '{formatter":{"third_party_settings":{"nomarkup":{"enabled":true}},"label":"above"}}' => $this->t('Without field markup, label above'),
      '{formatter":{"third_party_settings":{"nomarkup":{"enabled":true}},"label":"below"}}' => $this->t('Without field markup, label below'),
      '{formatter":{"third_party_settings":{"nomarkup":{"enabled":true}},"label":"hidden"}}' => $this->t('Without field markup, label hidden'),
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
