'use strict';

/**
 * Implements a timeout-based A/B test decider.
 */
class MockTracker extends BaseDecider {

  /**
   * Constructs a new TimeoutDecider instance.
   *
   * @param {boolean} debug
   *   Indicates if debug mode is enabled.
   * @param {string} apiKey
   *   The tracking service apiKey.
   * @param {Object} config
   *   Configuration object.
   * @param {string} config.trackingDomain
   *   The tracking domain.
   */
  constructor(debug, apiKey, config) {
    super(debug);
    this.apiKey = apiKey;
    this.trackingDomain = config.trackingDomain;
  }

  /**
   * Mocks tracking an event.
   *
   * @returns {Promise<Decision>}
   *   Resolves with the decision.
   */
  track() {
    return new Promise((resolve) => {
      setTimeout(() => {
        console.log('MockTracker: Event tracked successfully', this.apiKey, this.trackingDomain);
        resolve();
      }, 500);
    });
  }
}
