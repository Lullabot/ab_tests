G<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

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
   * @param string $title
   *   The human-readable name of the plugin.
   * @param string $description
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
    public readonly string $title,
    public readonly string $description,
    public readonly array $supported_features = [],
    public readonly string $decider_library = '',
  ) {
    parent::__construct($id);
  }

}
