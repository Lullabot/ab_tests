<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Plugin\AbAnalytics;

use Drupal\ab_tests\AbAnalyticsPluginBase;

/**
 * MockTracker analytics provider.
 *
 * @AbAnalytics(
 *   id = "null",
 *   label = @Translation("Null Tracker"),
 *   description = @Translation("Null tracker. It does not do any tracking. Use this only as a fallback."),
 *   analytics_library = "ab_tests/ab_analytics_tracker.null"
 * )
 */
class NullTracker extends AbAnalyticsPluginBase {}
