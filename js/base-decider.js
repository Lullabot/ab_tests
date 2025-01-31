((Drupal) => {
  'use strict';

  /**
   * Base class for A/B test deciders.
   */
  class BaseDecider {
    /**
     * Constructs a new BaseDecider instance.
     *
     * @param {string[]} variants
     *   Array of possible display mode variants.
     */
    constructor(variants) {
      this.variants = variants;
    }

    /**
     * Makes a decision about which variant to display.
     *
     * @returns {Promise<Decision>}
     *   Resolves with the decision.
     */
    decide() {
      throw new Error('Decider must implement decide() method.');
    }

    /**
     * Generates a unique decision ID.
     *
     * @returns {string}
     *   A unique identifier for this decision.
     */
    generateDecisionId() {
      return 'decision-' + crypto.randomUUID();
    }
  }

  // Make the class available globally.
  Drupal.BaseDecider = BaseDecider;
})(Drupal); 