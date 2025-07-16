/**
 * Base class for A/B test actions.
 *
 * @abstract
 */
class BaseAction {
  /**
   * Constructs a new BaseAction instance.
   */
  constructor() {
    this.debug = false;
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
   * @return {string}
   *   The status.
   */
  getStatus() {
    return this.status;
  }

  /**
   * Returns the error object.
   *
   * @return {Object|null}
   *   The error object, or null if no error.
   */
  getError() {
    return this.error;
  }

  /**
   * Retrieves the current value of the debug flag.
   *
   * @return {boolean}
   *   The current debug status.
   */
  getDebug() {
    return this.debug;
  }

  /**
   * Sets the debug flag.
   *
   * @param {boolean} value
   *   The value.
   */
  setDebug(value) {
    this.debug = value;
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
    this.getDebug() &&
      console.error(
        '[A/B Tests]',
        'There was an error during the A/B test.',
        error,
      );
  }
}
