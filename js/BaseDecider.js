'use strict';

/**
 * Base class for A/B test deciders.
 */
class BaseDecider {

  /**
   * Constructs a new BaseDecider instance.
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
   * Makes a decision about which variant to display.
   *
   * @returns {Promise<Decision>}
   *   Resolves with the decision.
   */
  decide() {
    throw new Error('Decider must implement decide() method.');
  }

  /**
   * Generates a unique decision ID.
   *
   * @returns {string}
   *   A unique identifier for this decision.
   */
  generateDecisionId() {
    return 'decision-' + crypto.randomUUID();
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
   * Gets the status of the decider.
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
    this.debug && console.error('A/B Tests', 'There was an error while deciding the variant.', error);
  }

}
