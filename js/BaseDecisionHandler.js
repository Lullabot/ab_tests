/**
 * Base class for A/B test actions.
 *
 * @abstract
 */
class BaseDecisionHandler {
  /**
   * Constructs a new BaseDecisionHandler instance.
   */
  constructor(settings, hideLoadingSkeleton, debug) {
    this.eventName = 'abTestFinished';
    this.settings = settings;
    this.debug = debug;
    // Initial status is 'idle'.
    this.status = 'idle';
    this.error = false;
    this.hideLoadingSkeleton = hideLoadingSkeleton;
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
    this.debug &&
      console.error(
        '[A/B Tests]',
        'There was an error during the A/B test.',
        error,
      );
  }

  /**
   * Handles the decision.
   *
   * Typically, it will call the server to re-render part of the page with the
   * new conditions decided by JS.
   *
   * @param {HTMLElement} element
   *   The element.
   * @param {Decision} decision
   *   The decision object.
   */
  async handleDecision(element, decision) {
    if (this._decisionChangesNothing(element, decision)) {
      this.hideLoadingSkeleton(element, this.debug);
    } else {
      try {
        await this._loadVariant(element, decision);
      } catch (error) {
        this._handleError(error, element, decision);
      }
    }

    // Dispatch decision event.
    this._dispatchCustomEvent(element, decision);
  }

  /**
   * Determines if the decision is the same that the server pre-rendered.
   *
   * @param {HTMLElement} element
   *   The element under test.
   * @param {Decision} decision
   *   The decision.
   *
   * @return {boolean}
   *   True if we don't need to pre-render. False if we do.
   *
   * @protected
   */
  _decisionChangesNothing(element, decision) {
    return false;
  }

  /**
   * Dispatch the custom event.
   *
   * This will be used by other parts of the application to subscribe to the
   * results of the test.
   *
   * @protected
   */
  _dispatchCustomEvent(element, decision) {
    const event = new CustomEvent(this.eventName, {
      detail: {},
      bubbles: true,
    });
    event.detail.element = element;
    event.detail.decision = decision;
    event.detail.status = this.status;
    event.detail.settings = this.settings;
    event.detail.error = this.error;
    this.debug &&
      console.debug(
        '[A/B Tests]',
        'Dispatching event after processing the new content.',
        event,
      );
    document.dispatchEvent(event);
    this.debug && console.debug('[A/B Tests]', 'Event dispatched.', event);
  }

  /**
   * Internal method to load a new variant via AJAX.
   *
   * IMPORTANT: this does nothing! It is meant to be overridden in the class
   * inheriting from this one.
   *
   * @param {HTMLElement} element
   *   The HTML element being A/B tested.
   * @param {Decision} decision
   *   The decision value.
   *
   * @return {Promise}
   *   Resolves when the variant is loaded.
   *
   * @private
   */
  async _loadVariant(element, decision) {
    return new Promise(resolve => {
      setTimeout(() => {
        this.debug &&
          console.debug(
            '[A/B Tests] A new empty variant was loaded. IMPORTANT: You need to override this method.',
          );
        resolve('');
      }, 1);
    });
  }

  /**
   * Handles errors loading the variant.
   *
   * @param {Error} error
   *   The error.
   * @param {HTMLElement} element
   *   The HTML element being A/B tested.
   * @param {Decision} decision
   *   The decision.
   *
   * @private
   */
  _handleError(error, element, decision) {
    this.error = true;
    this.debug &&
      console.debug(
        `[A/B Tests] There was an error loading the new variant after the decision`,
        error,
        decision,
      );
    // Show the loading default server-rendered variant.
    this.hideLoadingSkeleton(element, this.debug);
  }
}
