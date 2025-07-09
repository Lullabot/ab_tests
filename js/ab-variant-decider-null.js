((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    attach(context, { ab_tests: { deciderSettings, debug } }) {
      const elements = once(
        'ab-variant-decider-null',
        deciderSettings?.experimentsSelector,
        context,
      );

      if (!elements.length) {
        return;
      }

      const abTestsManager = new Drupal.AbTestsManager();
      elements.forEach(element => {
        const decider = new NullDecider();
        const uuid = element.getAttribute('data-ab-tests-entity-root');

        abTestsManager.registerDecider(uuid, decider, '', debug);
      });
    },
  };

})(Drupal, once);
