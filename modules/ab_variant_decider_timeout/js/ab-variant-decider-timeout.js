((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping AB test processing.');
        return;
      }

      const elements = once(
        'ab-variant-decider-timeout',
        '[data-ab-tests-entity-root]',
        context,
      );

      if (!elements.length) {
        return;
      }

      elements.forEach(element => {
        Drupal.abTests.registerElement(element);

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

        const decider = new TimeoutDecider(settings?.ab_tests?.debug || false, availableVariants, config);
        const uuid = element.getAttribute('data-ab-tests-entity-root');

        Drupal.abTests.registerDecider(uuid, decider);
      });
    },
  };

})(Drupal, once);
