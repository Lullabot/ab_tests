((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    async attach(context, { ab_tests: { debug } }) {
      if (
        context instanceof Document ||
        !context.hasAttribute('data-ab-tests-decision')) {
        return;
      }
      const abTestsManager = new AbTestsManager();
      await abTestsManager.registerTracker(context, new NullTracker(), debug);
    },
  };

})(Drupal, once);
