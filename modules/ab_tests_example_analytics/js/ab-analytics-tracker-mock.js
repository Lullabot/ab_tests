((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout tracker.
   */
  Drupal.behaviors.abVariantTrackerTimeout = {
    attach(context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping AB test processing.');
        return;
      }

      const elements = once(
        'ab-variant-tracker-timeout',
        '[data-ab-tests-entity-root]',
        context,
      );

      if (!elements.length) {
        return;
      }

      elements.forEach(element => {
        Drupal.abTests.registerElement(element);

        const trackerSettings = settings.ab_tests?.trackerSettings;
        if (!trackerSettings) {
          return;
        }

        const config = { trackingDomain: trackerSettings.trackingDomain };

        const tracker = new MockTracker(settings?.ab_tests?.debug || false, trackerSettings?.apiKey || '', config);
        const uuid = element.getAttribute('data-ab-tests-entity-root');

        Drupal.abTests.registerTracker(uuid, tracker);
      });
    },
  };

})(Drupal, once);
