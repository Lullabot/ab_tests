((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      const abTestsSettings = settings?.ab_tests || {};
      const { deciderSettings, debug = false } = abTestsSettings;
      
      if (!deciderSettings?.experimentsSelector) {
        return;
      }
      
      const elements = once(
        'ab-variant-decider-timeout',
        deciderSettings.experimentsSelector,
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

        // Add validation for timeout configuration
        const timeoutConfig = deciderSettings?.timeout || {};
        if (typeof timeoutConfig.min !== 'number' || typeof timeoutConfig.max !== 'number') {
          console.warn('[A/B Tests] Invalid timeout configuration, using defaults');
          timeoutConfig.min = 1000;
          timeoutConfig.max = 5000;
        }

        const config = {
          minTimeout: timeoutConfig.min,
          maxTimeout: timeoutConfig.max,
        };

        const decider = new TimeoutDecider(availableVariants, config);

        abTestsManager.registerDecider(
          element,
          decider,
          abTestsSettings,
          debug,
        );
      });
    },
  };

})(Drupal, once);
