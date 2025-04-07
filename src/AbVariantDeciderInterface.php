<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\Core\Render\RenderableInterface;

/**
 * Interface for ab_variant_decider plugins.
 */
interface AbVariantDeciderInterface extends UiPluginInterface, RenderableInterface {}
