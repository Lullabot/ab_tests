'use strict';

/**
 * Implements a null-pattern A/B test decider.
 */
class NullDecider extends BaseDecider {

  /**
   * Makes a decision after a random timeout.
   *
   * @returns {Promise<Decision>}
   *   Resolves with the decision.
   */
  decide() {
    return Promise.reject();
  }

}
