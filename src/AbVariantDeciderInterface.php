<?php

declare(strict_types = 1);

namespace Drupal\ab_tests;

use Drupal\Component\Render\MarkupInterface;

/**
 * Interface for ab_variant_decider plugins.
 */
interface AbVariantDeciderInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   */
  public function description(): MarkupInterface;

  /**
   * Creates the render array that will put the A/B variant decider on the page.
   *
   * @return array
   *   The render array.
   */
  public function build(): array;

}
