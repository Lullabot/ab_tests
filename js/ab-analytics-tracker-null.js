((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    attach(context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping A/B test processing.');
        return;
      }

      const elements = once(
        'ab-variant-decider-null',
        '[data-ab-tests-decision]',
        context,
      );

      elements.forEach(element => {
        Drupal.abTests.registerTracker(element, new NullDecider());
      });
    },
  };

})(Drupal, once);
