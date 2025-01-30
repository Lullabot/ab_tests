((Drupal, once) => {
  'use strict';

  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      const elements = once(
        'ab-variant-decider-timeout',
        '[data-ab-tests-entity-root]',
        context,
      );

      // @todo wire up an actual callback.
      const cb = console.log;

      if (!elements.length) {
        return;
      }

      const deciderSettings = settings.ab_tests?.deciderSettings || false;
      if (!deciderSettings) {
        cb(null);
        return;
      }
      // Get timeout settings and convert to integers.
      const max = parseInt(deciderSettings.timeout.max, 10);
      const min = parseInt(deciderSettings.timeout.min, 10);
      // Generate a random delay duration between min and max milliseconds.
      const duration = Math.floor(Math.random() * (max - min)) + min;

      // Extract enabled variants from settings object.
      // First convert object to entries, filter enabled ones (value is true),
      // then map to just the variant keys.
      const availableVariants = Object
        .entries(deciderSettings?.available_variants || [])
        .filter(([k, v]) => v)
        .map(([k]) => k);

      // If no variants are available, call callback with null after random delay.
      if (!availableVariants.length) {
        setTimeout(() => cb(null), duration);
        return;
      }

      // Randomly select one of the available variants.
      const index = Math.floor(
        Math.random() * availableVariants.length,
      );
      const displayMode = availableVariants[index];

      // If selected variant is invalid, call callback with null after random delay.
      if (!displayMode) {
        // Unable to find the correct display mode.
        setTimeout(() => cb(null), duration);
        return;
      }

      // Call callback with selected variant after random delay.
      setTimeout(() => cb(displayMode), duration);
    },
  };
})(Drupal, once);
