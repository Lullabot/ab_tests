((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderNull = {
    attach(context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping AB test processing.');
        return;
      }

      const elements = once(
        'ab-variant-decider-null',
        '[data-ab-tests-entity-root]',
        context,
      );

      if (!elements.length) {
        return;
      }

      elements.forEach(element => {
        Drupal.abTests.registerElement(element);

        const decider = new NullDecider();
        const uuid = element.getAttribute('data-ab-tests-entity-root');

        Drupal.abTests.registerDecider(uuid, decider);
      });
    },
  };

})(Drupal, once);
