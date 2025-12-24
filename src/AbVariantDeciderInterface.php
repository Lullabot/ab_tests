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
   * @param string $instance_id
   *   ID for this decider's instance. The same decider can appear several times
   *   in the page. This ID allows us to track different configuration for
   *   different blocks.
   * @param array $additional_settings
   *   Additional settings passed at render time.
   *
   * @return mixed[]
   *   A render array.
   */
  public function toRenderable(string $instance_id, array $additional_settings = []): array;

}
