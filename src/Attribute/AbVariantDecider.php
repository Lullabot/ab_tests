<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines ab_variant_decider attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AbVariantDecider extends Plugin {

  /**
   * Constructs an AbVariantDecider attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the plugin.
   * @param array $supported_features
   *   Supported features. Use this to restrict what features can use this
   *   decider. Features include 'ab_blocks', 'ab_view_modes', etc.
   *   Leave empty to support them all.
   * @param string $decider_library
   *   The library that will decide the variant using JS.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $supported_features = [],
    public readonly string $decider_library = '',
  ) {
    parent::__construct($id);
  }

}
