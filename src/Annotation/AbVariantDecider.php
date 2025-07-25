<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines ab_variant_decider annotation object.
 *
 * @Annotation
 */
final class AbVariantDecider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Supported features.
   *
   * Use this to restrict what features can use this decider. Features include
   * 'ab_blocks', 'ab_view_modes`, ...
   *
   * @var array
   *   The list of features. Leave empty to support them all.
   */
  public $supported_features = [];

  /**
   * The library that will decide the variant using JS.
   *
   * @var string
   */
  public string $decider_library;

}
