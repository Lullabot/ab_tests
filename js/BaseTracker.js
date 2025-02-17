'use strict';

/**
 * Base class for A/B test trackers.
 */
class BaseTracker extends BaseAction {

  /**
   * Makes a decision about which variant to display.
   *
   * @returns {Promise<Decision>}
   *   Resolves with the decision.
   */
  track() {
    throw new Error('Tracker must implement track() method.');
  }

}
