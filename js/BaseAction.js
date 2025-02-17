'use strict';

/**
 * Base class for A/B test actions.
 */
class BaseAction {

  /**
   * Constructs a new BaseTracker instance.
   *
   * @param {boolean} debug
   *   Indicates if the debug mode is on.
   */
  constructor(debug) {
    this.debug = debug;
    // Initial status is 'idle'.
    this.status = 'idle';
    this.error = null;
  }

  /**
   * Updates the status of the current instance.
   *
   * @param {string} status
   *   The new status to be set.
   */
  setStatus(status) {
    this.status = status;
  }

  /**
   * Gets the status of the tracker.
   *
   * @returns {string}
   *   The status.
   */
  getStatus() {
    return this.status;
  }

  /**
   * Returns the error object.
   *
   * @returns {Object|null}
   *   The error object, or null if no error.
   */
  getError() {
    return this.error;
  }

  /**
   * Handles errors encountered while deciding the variant.
   *
   * @param {Object} error
   *   The error object containing details of the encountered issue.
   */
  onError(error) {
    this.setStatus('error');
    this.error = error;
    this.debug && console.error('A/B Tests', 'There was an error during the A/B test.', error);
  }

}
