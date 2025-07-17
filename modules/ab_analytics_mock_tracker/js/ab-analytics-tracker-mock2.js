((Drupal, once) => {
  /**
   * Behavior to initialize timeout tracker.
   */
  Drupal.behaviors.abVariantTrackerTimeout2 = {
    async attach(context, settings) {
      if (
        context instanceof Document ||
        !context.hasAttribute('data-ab-tests-decision')
      ) {
        return;
      }
      const {
        ab_tests: { debug },
        ab_analytics_mock_tracker: { analyticsSettings },
      } = settings;
      const abTestsManager = new AbTestsManager();
      const apiKey = analyticsSettings?.apiKey || '';
      const config = {
        trackingDomain: analyticsSettings?.trackingDomain || '',
      };
      const tracker = new MockTracker2(apiKey, config);
      await abTestsManager.registerTracker(context, tracker, debug);
    },
  };
})(Drupal, once);
