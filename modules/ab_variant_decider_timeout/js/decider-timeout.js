((Drupal, once) => {
  'use strict';

  /**
   * Implements a timeout-based A/B test decider.
   */
  class TimeoutDecider extends Drupal.BaseDecider {
    /**
     * Constructs a new TimeoutDecider instance.
     *
     * @param {Object} config
     *   Configuration object.
     * @param {number} config.minTimeout
     *   Minimum timeout in milliseconds.
     * @param {number} config.maxTimeout
     *   Maximum timeout in milliseconds.
     * @param {string[]} variants
     *   Array of possible display mode variants.
     */
    constructor(config, variants) {
      super(variants);
      this.minTimeout = parseInt(config.minTimeout, 10);
      this.maxTimeout = parseInt(config.maxTimeout, 10);
    }

    /**
     * Makes a decision after a random timeout.
     *
     * @returns {Promise<Decision>}
     *   Resolves with the decision.
     */
    decide() {
      return new Promise((resolve) => {
        const duration = Math.floor(Math.random() * (this.maxTimeout - this.minTimeout)) + this.minTimeout;
        const displayMode = this.variants[Math.floor(Math.random() * this.variants.length)];

        setTimeout(() => {
          resolve(new Drupal.abTestsDecision(
            this.generateDecisionId(),
            displayMode,
            { 
              timeout: duration,
              deciderId: 'timeout'
            }
          ));
        }, duration);
      });
    }
  }

  /**
   * Behavior to initialize timeout decider.
   */
  Drupal.behaviors.abVariantDeciderTimeout = {
    attach(context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping AB test processing.');
        return;
      }

      const elements = once(
        'ab-variant-decider-timeout',
        '[data-ab-tests-entity-root]',
        context,
      );

      if (!elements.length) {
        return;
      }

      elements.forEach(element => {
        Drupal.abTests.registerElement(element);
        
        const deciderSettings = settings.ab_tests?.deciderSettings;
        if (!deciderSettings) {
          return;
        }

        // Extract enabled variants from settings.
        const availableVariants = Object
          .entries(deciderSettings?.available_variants || [])
          .filter(([k, v]) => v)
          .map(([k]) => k);

        if (!availableVariants.length) {
          return;
        }

        const config = {
          minTimeout: deciderSettings.timeout.min,
          maxTimeout: deciderSettings.timeout.max
        };

        const decider = new TimeoutDecider(config, availableVariants);
        const uuid = element.getAttribute('data-ab-tests-entity-root');
        
        Drupal.abTests.registerDecider(uuid, decider);
      });
    },
  };

  // Make the class available globally.
  Drupal.TimeoutDecider = TimeoutDecider;
})(Drupal, once);
