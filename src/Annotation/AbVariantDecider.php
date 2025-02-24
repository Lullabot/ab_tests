<?php

declare(strict_types=1);

declare(strict_types = 1);

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
   */
  public readonly string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $title;

  /**
   * The description of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $description;

  /**
   * The library that will decide the variant using JS.
   */
  public readonly string $decider_library;

}
