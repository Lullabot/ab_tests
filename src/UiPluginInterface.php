<?php

namespace Drupal\ab_tests;

use Drupal\Component\Render\MarkupInterface;

/**
 * Interface for plugins that have UI elements.
 */
interface UiPluginInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The translated plugin description.
   */
  public function description(): MarkupInterface;

}
