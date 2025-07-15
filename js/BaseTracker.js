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
    const fun = ((e) => e);
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
   * @return {Promise<unknown>}
   *   Resolves with the tracking.
   *
   * @throws {Error}
   *   When there is an error.
   * @abstract
   */
  track(decision, element) {
    throw new Error('Tracker must implement track() method.');
  }
}
