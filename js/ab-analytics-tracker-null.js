((Drupal, once) => {
  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abAnalyticsTrackerNull = {
    async attach(context, settings) {
      if (
        !(context instanceof HTMLElement) ||
        !context.hasAttribute('data-ab-tests-tracking-info')
      ) {
        return;
      }
      const {
        ab_tests: { debug },
      } = settings;
      const abTestsManager = new AbTestsManager();
      await abTestsManager.registerTracker(context, new NullTracker(), debug);
    },
  };
})(Drupal, once);
