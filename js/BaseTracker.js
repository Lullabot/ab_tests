/**
 * Base class for A/B test trackers.
 *
 * @abstract
 */
class BaseTracker extends BaseAction {
  /**
   * Initialize the tracker.
   *
   * @return {Promise<unknown>}
   *   The promise of initialization.
   */
  initialize() {
    return Promise.resolve();
  }

  /**
   * Makes a decision about which variant to display.
   *
   * @param {string} trackingInfo
   *   The tracing information from the server.
   * @param {HTMLElement} element
   *   The element under test.
   *
   * @return {Promise<unknown>}
   *   Resolves with the tracking.
   *
   * @throws {Error}
   *   When there is an error.
   *
   * @protected
   */
  track(trackingInfo, element) {
    throw new Error('Tracker must implement track() method.');
  }
}
