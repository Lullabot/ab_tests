((document, Drupal, drupalSettings) => {
  'use strict';

  /**
   * Manages A/B test decisions and variant switching.
   */
  class AbTestsManager {

    eventName = 'abTestFinished';

    /**
     * Registers a decider for a specific test.
     *
     * @param {HTMLElement} element
     *   The element.
     * @param {BaseDecider} decider
     *   The decider instance that implements decide().
     * @param {string} defaultDecisionValue
     *   The default decision. IMPORTANT: this needs to be a serialized string,
     *   so we can compare with the actual decision.
     * @param {boolean} debug
     *   Weather to add debug messages to the console.
     *
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    registerDecider(element, decider, defaultDecisionValue, debug = false) {
      const status = 'pending';
      decider.setStatus(status);
      decider.setDebug(debug);
      element.setAttribute('data-ab-tests-decider-status', status);

      // Start the decision process.
      return this._makeDecision(element, decider, defaultDecisionValue, debug);
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
     * @returns {Promise<Decision>}
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
     * @param {string} defaultDecisionValue
     *   The default decision value.
     * @param {boolean} debug
     *   Weather to add debug messages to the console.
     *
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    async _makeDecision(element, decider, defaultDecisionValue, debug) {
      try {
        debug && console.debug('[A/B Tests]', 'A decision is about to be made.');
        const decision = await decider.decide(element);
        const status = 'success';
        decider.setStatus(status);
        // Set a data attribute to indicate the test is in progress.
        element.setAttribute('data-ab-tests-decision-status', status);
        debug && console.debug('[A/B Tests]', 'A decision was reached.', decision);
        await this._handleDecision(element, decision, defaultDecisionValue, debug);
        return decision;
      } catch (error) {
        decider.onError(error);
        debug && console.error('[A/B Tests]', 'Decision failed:', error);
        // On error, show the default variant.
        showDefaultVariant(debug)(element);
      }
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
     * @returns {Promise<Decision>}
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
     * Internal method to handle a decision once made.
     *
     * @param {HTMLElement} element
     *   The element.
     * @param {Decision} decision
     *   The decision object.
     * @param {string} defaultDecisionValue
     *   The default decision value.
     * @param {boolean} debug
     *   Weather to add debug messages to the console.
     */
    async _handleDecision(element, decision, defaultDecisionValue, debug) {
      const uuid = element.getAttribute('data-ab-tests-entity-root');
      if (decision.decisionValue === defaultDecisionValue) {
        showDefaultVariant(debug)(element);
      } else {
        await this._loadVariant(uuid, decision.decisionValue, debug);
      }

      // Dispatch decision event.
      const event = new CustomEvent(this.eventName, {
        detail: {
          uuid,
          decision,
        },
        bubbles: true,
      });
      debug && console.debug('[A/B Tests]', 'Dispatching event after showing the content.', event);
      element.dispatchEvent(event);
      debug && console.debug('[A/B Tests]', 'Event dispatched.', event);
    }

    /**
     * Internal method to load a new variant via AJAX.
     *
     * @param {string} uuid
     *   The UUID of the entity.
     * @param {string} displayMode
     *   The display mode to load.
     * @param {boolean} debug
     *   Weather to add debug messages to the console.
     *
     * @returns {Promise}
     *   Resolves when the variant is loaded.
     */
    _loadVariant(uuid, displayMode, debug) {
      debug && console.debug('[A/B Tests]', 'Requesting node to be rendered via Ajax.', uuid, displayMode);
      return new Promise((resolve, reject) => {
        Drupal.ajax({
          url: `/ab-tests/render/${uuid}/${displayMode}`,
          httpMethod: 'GET',
        }).execute()
          .then(response => {
            debug && console.debug('[A/B Tests]', 'The entity was rendered with the new view mode.', uuid);
            return response;
          })
          .then(resolve)
          .catch(error => {
            debug && console.debug('[A/B Tests]', 'There was an error rendering the entity: ', JSON.stringify(error), uuid);
            reject(error);
          });
      });
    }

  }


  /**
   * Show loading skeleton.
   */
  const showSkeleton = (debug) => (element) => {
    debug && console.debug('[A/B Tests]', 'Turning the default A/B Test view mode into the page skeleton');
    element.classList.add('ab-test-loading');
  }

  /**
   * Show the default variant.
   */
  const showDefaultVariant = (debug) => (element) => {
    debug && console.debug('[A/B Tests]', 'Un-hiding the default variant.');
    element.classList.remove('ab-test-loading');
    debug && console.debug('[A/B Tests]', 'Default variant un-hidden.');
  }

  Drupal.AbTestsManager = AbTestsManager;
  Drupal.behaviors.abTests = {
    attach: function (context, { ab_tests: { debug } }) {
      const elements = once(
        'ab-tests-element',
        // All the A/B tests should render from the server side with this data
        // attribute.
        '[data-ab-tests-decider-status="idle"]',
        context,
      );

      // @todo I should remove the "debug" checkbox from all features. Instead it should live in a centralized place. Until that happens, I am hard-coding debug=true.
      elements.forEach(showSkeleton(debug));
    },
  };

})(document, Drupal, drupalSettings);
