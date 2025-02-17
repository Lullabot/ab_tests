((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping A/B test processing.');
        return;
      }

      const elements = once(
        'ab-variant-decider-timeout',
        '[data-ab-tests-entity-root]',
        context,
      );

      elements.forEach(element => {
        const deciderSettings = settings.ab_tests?.deciderSettings;
        if (!deciderSettings) {
          return;
        }

        // Extract enabled variants from settings.
        const availableVariants = Object
          .entries(deciderSettings?.available_variants || [])
          .filter(([k, v]) => v)
          .map(([k]) => k);

        if (!availableVariants.length) {
          return;
        }

        const config = {
          minTimeout: deciderSettings.timeout.min,
          maxTimeout: deciderSettings.timeout.max
        };

        const decider = new TimeoutDecider(availableVariants, config);

        Drupal.abTests.registerDecider(element, decider);
      });
    },
  };

})(Drupal, once);
