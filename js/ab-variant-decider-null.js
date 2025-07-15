((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    attach(context, settings) {
      const abTestsSettings = settings?.ab_tests || {};
      const { deciderSettings, debug = false } = abTestsSettings;
      
      if (!deciderSettings?.experimentsSelector) {
        // Fallback selector if not configured
        const fallbackSelector = '[data-ab-tests-decider-status="idle"]';
        deciderSettings = { experimentsSelector: fallbackSelector };
      }
      
      const elements = once(
        'ab-variant-decider-null',
        deciderSettings.experimentsSelector,
        context,
      );

      if (!elements.length) {
        return;
      }

      const abTestsManager = new AbTestsManager();
      elements.forEach(element => {
        const decider = new NullDecider();

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
