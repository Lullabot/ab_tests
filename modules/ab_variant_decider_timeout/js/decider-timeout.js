((Drupal, once) => {
  'use strict';

  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      const elements = once(
        'ab-variant-decider-timeout',
        '[ab-tests-entity-root]',
        context,
      );
      if (!elements.length) {
        return;
      }
      // Random number between 200 and 1000.
      const duration = Math.floor(Math.random() * 800) + 200;
      const index = Math.floor(
        Math.random() * (settings.ab_tests?.displayModes?.length || 0),
      );
      const displayMode = settings.ab_tests?.displayModes[index];
      if (!displayMode) {
        // Unable to find the correct display mode.
        console.log('ab_tests', 'Lorem Ipsum');
      }
    },
  };
})(Drupal, once);
