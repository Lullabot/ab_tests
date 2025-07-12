((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      const { ab_tests: { deciderSettings, debug } } = settings;
      const elements = once(
        'ab-variant-decider-timeout',
        deciderSettings?.experimentsSelector,
        context,
      );

      const abTestsManager = new AbTestsManager();
      elements.forEach(element => {
        if (!deciderSettings) {
          return;
        }

        // Extract enabled variants from settings.
        const availableVariants = deciderSettings?.availableVariants || [];

        if (!availableVariants.length) {
          return;
        }

        const config = {
          minTimeout: deciderSettings.timeout.min,
          maxTimeout: deciderSettings.timeout.max,
        };

        const decider = new TimeoutDecider(availableVariants, config);

        abTestsManager.registerDecider(
          element,
          decider,
          settings.ab_tests || {},
          debug,
        );
      });
    },
  };

})(Drupal, once);
