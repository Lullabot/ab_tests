((Drupal, once) => {
  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abAnalyticsTrackerNull = {
    async attach(context, settings) {
      const abTestsSettings = settings?.ab_tests || {};
      const { debug = false } = abTestsSettings;

      if (
        context instanceof Document ||
        !context.hasAttribute('data-ab-tests-decision')
      ) {
        return;
      }
      const abTestsManager = new AbTestsManager();
      await abTestsManager.registerTracker(context, new NullTracker(), debug);
    },
  };
})(Drupal, once);
