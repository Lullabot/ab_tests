<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an AB Analytics plugin annotation object.
 *
 * @Annotation
 */
class AbAnalytics extends Plugin {

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
   * A brief description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The analytics library to attach.
   *
   * @var string
   */
  public string $analytics_library;

}
