'use strict';

/**
 * Implements a null-pattern A/B test decider.
 */
class NullDecider extends BaseDecider {

  /**
   * Refuses to decide.
   *
   * @returns {Promise<Decision>}
   *   Resolves with the decision.
   */
  decide() {
    return Promise.reject();
  }

}
