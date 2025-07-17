<?php

declare(strict_types=1);

namespace Drupal\ab_analytics_mock_tracker\Plugin\AbAnalytics;

use Drupal\ab_analytics_tracker_example\Plugin\AbAnalytics\MockTracker;

/**
 * MockTracker analytics provider.
 *
 * @AbAnalytics(
 *   id = "mock_tracker_2",
 *   label = @Translation("Mock Tracker 2"),
 *   description = @Translation("Example analytics provider for testing"),
 *   analytics_library = "ab_analytics_mock_tracker/ab_analytics_tracker.mock2"
 * )
 */
class MockTracker2 extends MockTracker {}
