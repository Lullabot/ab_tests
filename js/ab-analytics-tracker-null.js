((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
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
      await Drupal.abTests.registerTracker(context, new NullTracker());
    },
  };

})(Drupal, once);
