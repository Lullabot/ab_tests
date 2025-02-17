<?php

namespace Drupal\ab_tests;

use Drupal\Core\Render\RenderableInterface;

/**
 * Interface for AB test analytics providers.
 */
interface AbAnalyticsInterface extends UiPluginInterface, RenderableInterface {}
