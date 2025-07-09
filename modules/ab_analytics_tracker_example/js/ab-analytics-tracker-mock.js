((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout tracker.
   */
  Drupal.behaviors.abVariantTrackerTimeout = {
    async attach(context, { ab_tests: { analyticsSettings, debug } }) {
      if (
        context instanceof Document ||
        !context.hasAttribute('data-ab-tests-decision')) {
        return;
      }
      const abTestsManager = new Drupal.AbTestsManager();
      const apiKey = analyticsSettings?.apiKey || '';
      const config = { trackingDomain: analyticsSettings?.trackingDomain || '' };
      const tracker = new MockTracker(apiKey, config);
      await abTestsManager.registerTracker(context, tracker, debug);
    },
  };

})(Drupal, once);
