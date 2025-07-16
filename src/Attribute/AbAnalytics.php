<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines an AB Analytics plugin attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AbAnalytics extends Plugin {

  /**
   * Constructs an AbAnalytics attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $label
   *   The human-readable name of the plugin.
   * @param string $description
   *   A brief description of the plugin.
   * @param array $supported_features
   *   Supported features. Use this to restrict what features can use this
   *   decider. Features include 'ab_blocks', 'ab_view_modes', etc.
   *   Leave empty to support them all.
   * @param string $analytics_library
   *   The analytics library to attach.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $label,
    public readonly string $description,
    public readonly array $supported_features = [],
    public readonly string $analytics_library = '',
  ) {
    parent::__construct($id);
  }

}
