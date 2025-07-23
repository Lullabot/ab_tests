((Drupal, once) => {
  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    attach(context, settings) {
      const debug = settings?.ab_tests?.debug || false;
      const abTestsManager = new AbTestsManager();
      Object.values(settings?.ab_tests || {}).forEach(abTestsSettings => {
        const { deciderSettings } = abTestsSettings;

        if (!deciderSettings?.experimentsSelector) {
          return;
        }

        once(
          'ab-variant-decider-null',
          actualDeciderSettings.experimentsSelector,
          context,
        ).forEach(element => {
          const decider = new NullDecider();

          abTestsManager.registerDecider(
            element,
            decider,
            abTestsSettings,
            debug,
          );
        });
      });
    },
  };
})(Drupal, once);
