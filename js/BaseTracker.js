'use strict';

/**
 * Base class for A/B test trackers.
 *
 * @abstract
 */
class BaseTracker extends BaseAction {

  /**
   * Initialize the tracker.
   */
  initialize() {
    return Promise.resolve();
  }

  /**
   * Makes a decision about which variant to display.
   *
   * @param {Decision} decision
   *   The A/B test decision.
   * @param {HTMLElement} element
   *   The element under test.
   *
   * @returns {Promise<unknown>}
   *   Resolves with the tracking.
   */
  track(decision, element) {
    throw new Error('Tracker must implement track() method.');
  }

}
