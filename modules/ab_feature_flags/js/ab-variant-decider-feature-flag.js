/* global LaunchDarklyDecider, AbTestsManager */
((Drupal, once) => {
  'use strict';

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderFeatureFlag = {
    attach(context, settings) {
      const debug = settings?.ab_tests?.debug || false;
      const abTestsManager = new AbTestsManager();
      Object.values(settings?.ab_feature_flags || {}).forEach(
        (abTestsSettings) => {
          const experimentsSelector =
            abTestsSettings?.deciderSettings?.experimentsSelector;
          if (!experimentsSelector) {
            return;
          }
          const flagId = abTestsSettings?.deciderSettings?.flag_id || null;
          if (!flagId) {
            return;
          }

          const elements = once(
            'ab-variant-decider-launchdarkly',
            experimentsSelector,
            context,
          );

          elements.forEach((element) => {
            // Hide the blog post using JS while the decision is being made.
            element.classList.add('ab-test-hidden');
            document.addEventListener('ab_tests:abTestFinished', (event) => {
              event.detail.element.classList.remove('ab-test-hidden');
            });

            const decider = new FeatureFlagDecider(flagId);
            abTestsManager.registerDecider(
              element,
              decider,
              settings.ab_tests,
              debug,
            );
          });
        },
      );
    },
  };
})(Drupal, once);
