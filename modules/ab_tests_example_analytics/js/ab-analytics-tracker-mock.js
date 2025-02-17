((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout tracker.
   */
  Drupal.behaviors.abVariantTrackerTimeout = {
    async attach(context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping A/B test processing.');
        return;
      }

      if (
        context instanceof Document ||
        !context.hasAttribute('data-ab-tests-decision')) {
        return;
      }
      const trackerSettings = settings.ab_tests?.analyticsSettings;
      const apiKey = trackerSettings?.apiKey || '';
      const config = { trackingDomain: trackerSettings?.trackingDomain || '' };
      const tracker = new MockTracker(apiKey, config);
      await Drupal.abTests.registerTracker(context, tracker);
    },
  };

})(Drupal, once);
