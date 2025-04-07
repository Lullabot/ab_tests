'use strict';

/**
 * Implements a timeout-based A/B test decider.
 */
class TimeoutDecider extends BaseDecider {

  /**
   * Constructs a new TimeoutDecider instance.
   *
   * @param {string[]} variants
   *   Array of possible display mode variants.
   * @param {Object} config
   *   Configuration object.
   * @param {number} config.minTimeout
   *   Minimum timeout in milliseconds.
   * @param {number} config.maxTimeout
   *   Maximum timeout in milliseconds.
   */
  constructor(variants, config) {
    super();
    this.variants = variants;
    this.minTimeout = parseInt(config.minTimeout, 10);
    this.maxTimeout = parseInt(config.maxTimeout, 10);
  }

  /**
   * @inheritDoc
   */
  decide(element) {
    return new Promise((resolve) => {
      const duration = Math.floor(Math.random() * (this.maxTimeout - this.minTimeout)) + this.minTimeout;
      const randomIndex = Math.floor(Math.random() * this.variants.length);
      const displayMode = this.variants[randomIndex];
      const decisionId = this.generateDecisionId();
      this.getDebug() && console.debug('[A/B Tests]', duration, displayMode, decisionId);

      setTimeout(() => {
        resolve(new Decision(
          decisionId,
          displayMode,
          {
            timeout: duration,
            deciderId: 'timeout',
          },
        ));
      }, duration);
    });
  }
}
