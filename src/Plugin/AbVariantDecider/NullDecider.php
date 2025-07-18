<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Plugin\AbVariantDecider;

use Drupal\ab_tests\AbVariantDeciderPluginBase;
use Drupal\ab_tests\Attribute\AbVariantDecider;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the ab_variant_decider.
 */
#[AbVariantDecider(
  id: 'null',
  label: new TranslatableMarkup('Null'),
  description: new TranslatableMarkup('A decider that always errors out. Only useful for QA, and debugging. Do not use in production.'),
  decider_library: 'ab_tests/ab_variant_decider.null',
)]
final class NullDecider extends AbVariantDeciderPluginBase {}
