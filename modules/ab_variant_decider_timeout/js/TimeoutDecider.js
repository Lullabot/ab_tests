'use strict';

/**
 * Implements a timeout-based A/B test decider.
 */
class TimeoutDecider extends BaseDecider {

  /**
   * Constructs a new TimeoutDecider instance.
   *
   * @param {boolean} debug
   *   Indicates if debug mode is enabled.
   * @param {string[]} variants
   *   Array of possible display mode variants.
   * @param {Object} config
   *   Configuration object.
   * @param {number} config.minTimeout
   *   Minimum timeout in milliseconds.
   * @param {number} config.maxTimeout
   *   Maximum timeout in milliseconds.
   */
  constructor(debug, variants, config) {
    super(debug);
    this.variants = variants;
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
        resolve(new Decision(
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
