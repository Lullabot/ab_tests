((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    attach(context, settings) {
      const { ab_tests: { deciderSettings, debug } } = settings;
      const elements = once(
        'ab-variant-decider-null',
        deciderSettings?.experimentsSelector,
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
          settings.ab_tests || {},
          debug,
        );
      });
    },
  };

})(Drupal, once);
