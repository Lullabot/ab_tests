((Drupal, once) => {
  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    attach(context, settings) {
      const abTestsSettings = settings?.ab_tests || {};
      const { deciderSettings, debug = false } = abTestsSettings;

      let actualDeciderSettings = deciderSettings;
      if (!actualDeciderSettings?.experimentsSelector) {
        // Fallback selector if not configured
        const fallbackSelector = '[data-ab-tests-decider-status="idle"]';
        actualDeciderSettings = { experimentsSelector: fallbackSelector };
      }

      const elements = once(
        'ab-variant-decider-null',
        actualDeciderSettings.experimentsSelector,
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
