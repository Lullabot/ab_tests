<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

/**
 * Interface for ab_variant_decider plugins.
 */
interface AbVariantDeciderInterface extends UiPluginInterface {

  /**
   * Returns a render array representation of the object.
   *
   * @param array $additional_settings
   *   Additional settings passed at render time.
   *
   * @return mixed[]
   *   A render array.
   */
  public function toRenderable($additional_settings = []): array;

}
