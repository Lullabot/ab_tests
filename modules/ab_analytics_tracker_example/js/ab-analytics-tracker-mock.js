((Drupal, once) => {
  /**
   * Behavior to initialize timeout tracker.
   */
  Drupal.behaviors.abVariantTrackerTimeout = {
    async attach(context, settings) {
      if (
        context instanceof Document ||
        !context.hasAttribute('data-ab-tests-tracking-info')
      ) {
        return;
      }
      const {
        ab_tests: { debug },
        ab_analytics_tracker_example: { analyticsSettings },
      } = settings;
      const abTestsManager = new AbTestsManager();
      const apiKey = analyticsSettings?.apiKey || '';
      const config = {
        trackingDomain: analyticsSettings?.trackingDomain || '',
      };
      const tracker = new MockTracker(apiKey, config);
      await abTestsManager.registerTracker(context, tracker, debug);
    },
  };
})(Drupal, once);
