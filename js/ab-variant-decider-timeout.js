((Drupal, once) => {
  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      const debug = settings?.ab_tests?.debug || false;
      const abTestsManager = new AbTestsManager();
      Object.values(settings?.ab_variant_decider_launchdarkly || {}).forEach(
        abTestsSettings => {
          const { deciderSettings } = abTestsSettings;

          if (!deciderSettings?.experimentsSelector) {
            return;
          }

          const elements = once(
            'ab-variant-decider-timeout',
            deciderSettings.experimentsSelector,
            context,
          );

          elements.forEach(element => {
            // Extract enabled variants from settings.
            const availableVariants = deciderSettings?.availableVariants || [];

            if (!availableVariants.length) {
              return;
            }

            const timeoutConfig = deciderSettings?.timeout || {};
            const config = {
              minTimeout: parseInt(timeoutConfig?.min, 10) || 1000,
              maxTimeout: parseInt(timeoutConfig?.max, 10) || 5000,
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
      );
    },
  };
})(Drupal, once);
