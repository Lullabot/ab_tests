'use strict';

/**
 * Implements a null-pattern A/B test tracker.
 */
class NullTracker extends BaseTracker {

  /**
   * Refuses to track.
   *
   * @returns {Promise<Decision>}
   *   Resolves with the decision.
   */
  track() {
    return Promise.reject();
  }

}
