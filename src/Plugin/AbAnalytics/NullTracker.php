<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Plugin\AbAnalytics;

use Drupal\ab_tests\AbAnalyticsPluginBase;
use Drupal\ab_tests\Attribute\AbAnalytics;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * MockTracker analytics provider.
 */
#[AbAnalytics(
  id: 'null',
  label: new TranslatableMarkup('Null Tracker'),
  description: new TranslatableMarkup('Null tracker. It does not do any tracking. Use this only as a fallback.'),
  analytics_library: 'ab_tests/ab_analytics_tracker.null',
)]
class NullTracker extends AbAnalyticsPluginBase {}
