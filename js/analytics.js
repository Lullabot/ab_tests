/**
 * @file
 * AB Tests analytics handling.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.abTestsAnalytics = {
    attach: function (context, settings) {
      if (!settings.abTests?.analytics) {
        return;
      }

      // Initialize configured analytics providers
      Object.entries(settings.abTests.analytics).forEach(([provider, config]) => {
        if (provider === 'mock_tracker' && Drupal.MockTracker) {
          Drupal.MockTracker.init(config);
        }
        // Add other provider initializations here
      });
    },
  };

})(Drupal);
