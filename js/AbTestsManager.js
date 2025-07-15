/**
 * Manages A/B test decisions and variant switching.
 */
class AbTestsManager {
  /**
   * Registers a decider for a specific test.
   *
   * @param {HTMLElement} element
   *   The element.
   * @param {BaseDecider} decider
   *   The decider instance that implements decide().
   * @param {Object} settings
   *   The drupalSettings.
   * @param {boolean} debug
   *   Weather to add debug messages to the console.
   *
   * @return {Promise<Decision>}
   *   Resolves with the decision once made.
   */
  registerDecider(element, decider, settings, debug = false) {
    const status = 'pending';
    decider.setStatus(status);
    decider.setDebug(debug);
    element.setAttribute('data-ab-tests-decider-status', status);

    // Each A/B test method (view mode, block, entity, ...) will handle
    // re-rendering differently. Each one of their corresponding modules is in
    // charge of writing a JS class that inherits from BaseDecisionHandler
    // that does that. They are also in charge of putting a hint in the DOM
    // on what decision handler to use for each element under test. For this,
    // we use the convention
    // data-ab-tests-feature="ab_view_modes/ab_blocks/ab_entity/...". With
    // this, we can call the decision handler factory to take care of each
    // element in the page (if more than one).
    const decisionHandler = new DecisionHandlerFactory().createInstance(
      element.getAttribute('data-ab-tests-feature'),
      settings,
      debug,
      this.hideLoadingSkeleton,
    );

    // Start the decision process.
    return this._makeDecision(element, decider, decisionHandler, debug);
  }

  /**
   * Registers a tracker for a specific test.
   *
   * @param {HTMLElement} element
   *   The element of the A/B test.
   * @param {BaseTracker} tracker
   *   The tracker instance that implements track().
   * @param {boolean} debug
   *   Weather to add debug messages to the console.
   *
   * @return {Promise<Decision>}
   *   Resolves with the decision once made.
   */
  async registerTracker(element, tracker, debug = false) {
    let status = 'initializing';
    tracker.setStatus(status);
    tracker.setDebug(debug);
    debug && console.debug('[A/B Tests]', 'Initializing tracker.');
    element.setAttribute('data-ab-tests-tracker-status', status);
    await tracker.initialize();
    debug && console.debug('[A/B Tests]', 'Tracker initialized.');
    status = 'pending';
    tracker.setStatus(status);
    element.setAttribute('data-ab-tests-tracker-status', status);
    // Start the tracking process.
    const eventInfo = {
      tracker,
      decision: element.getAttribute('data-ab-tests-decision'),
    };
    return this._doTrack(element, eventInfo, debug);
  }

  /**
   * Internal method to make a decision using the registered decider.
   *
   * @param {HTMLElement} element
   *   The DOM element.
   * @param {BaseDecider} decider
   *   The decider.
   * @param {BaseDecisionHandler} decisionHandler
   *   The object that will do stuff based on the decision.
   * @param {boolean} debug
   *   Weather to add debug messages to the console.
   *
   * @return {Promise<Decision>}
   *   Resolves with the decision once made.
   */
  async _makeDecision(element, decider, decisionHandler, debug) {
    let status = 'pending';
    let decision = null;
    try {
      debug && console.debug('[A/B Tests]', 'A decision is about to be made.');
      decision = await decider.decide(element);
      status = 'success';
      debug &&
        console.debug('[A/B Tests]', 'A decision was reached.', decision);
      await decisionHandler.handleDecision(element, decision);
    } catch (error) {
      status = 'error';
      decider.onError(error);
      debug && console.error('[A/B Tests]', 'Decision failed:', error);
      // On error, show the default variant.
      this.hideLoadingSkeleton(element, debug);
    } finally {
      decider.setStatus(status);
      // Set a data attribute to indicate the test is in progress.
      element.setAttribute('data-ab-tests-decider-status', status);
    }
    return decision;
  }

  /**
   * Internal method to track a test result using the registered tracker.
   *
   * @param {HTMLElement} element
   *   The element.
   * @param {Object} eventInfo
   *   The event info object.
   * @param {BaseTracker} eventInfo.tracker
   *   The tracker instance that implements track().
   * @param {Decision} eventInfo.decision
   *   The decision object.
   * @param {boolean} debug
   *   Weather to add debug messages to the console.
   *
   * @return {Promise<Decision>}
   *   Resolves with the tracking made.
   */
  async _doTrack(element, { tracker, decision }, debug) {
    try {
      debug && console.debug('[A/B Tests]', 'Tracking is about to start.');
      const result = await tracker.track(decision, element);
      const status = 'success';
      tracker.setStatus(status);
      element.setAttribute('data-ab-tests-tracker-status', status);
      debug && console.debug('[A/B Tests]', 'Tracking was successful.', result);
      return result;
    } catch (error) {
      tracker.onError(error);
      debug && console.error('[A/B Tests]', 'Tracking failed:', error);
    }
  }

  /**
   * Show the default variant.
   */
  hideLoadingSkeleton(element, debug) {
    debug && console.debug('[A/B Tests]', 'Un-hiding the default variant.');
    element.classList.remove('ab-test-loading');
  }
}
