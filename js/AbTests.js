((document, Drupal, drupalSettings) => {
  'use strict';

  /**
   * Manages A/B test decisions and variant switching.
   */
  class AbTests {

    eventName = 'abTestFinished';
    debug = false;
    defaultViewMode = 'default';

    /**
     * Creates AbTests objects.
     *
     * This uses a singleton pattern.
     *
     * @param {boolean} debug
     *   TRUE if debug mode is enabled.
     * @param {string} defaultViewMode
     *   The default view mode.
     *
     * @returns {AbTests}
     *   The singleton instance.
     */
    constructor(debug, defaultViewMode) {
      if (AbTests.instance) {
        return AbTests.instance;
      }

      this.defaultViewMode = defaultViewMode;
      this.debug = debug;
      AbTests.instance = this;
    }

    /**
     * Registers an element for A/B testing.
     *
     * @param {HTMLElement} element
     *   The root element for this A/B test.
     * @returns {AbTests}
     *   The singleton instance.
     */
    registerElement(element) {
      element.setAttribute('data-ab-tests-decider-status', 'idle');

      // Hide the default variant and show skeleton.
      this._showSkeleton(element);
    }

    /**
     * Registers a decider for a specific test.
     *
     * @param {HTMLElement} element
     *   The element.
     * @param {BaseDecider} decider
     *   The decider instance that implements decide().
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    registerDecider(element, decider) {
      const status = 'pending';
      decider.setStatus(status);
      decider.setDebug(this.debug);
      element.setAttribute('data-ab-tests-decider-status', status);

      // Start the decision process.
      return this._makeDecision(element, decider);
    }

    /**
     * Registers a tracker for a specific test.
     *
     * @param {HTMLElement} element
     *   The element of the A/B test.
     * @param {BaseTracker} tracker
     *   The tracker instance that implements track().
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    async registerTracker(element, tracker) {
      let status = 'initializing';
      tracker.setStatus(status);
      tracker.setDebug(this.debug);
      this.debug && console.debug('A/B Tests', 'Initializing tracker.');
      element.setAttribute('data-ab-tests-tracker-status', status);
      await tracker.initialize();
      this.debug && console.debug('A/B Tests', 'Tracker initialized.');
      status = 'pending';
      tracker.setStatus(status);
      element.setAttribute('data-ab-tests-tracker-status', status);
      // Start the tracking process.
      const eventInfo = {
        tracker,
        decision: element.getAttribute('data-ab-tests-decision'),
      };
      return this._doTrack(element, eventInfo);
    }

    /**
     * Internal method to make a decision using the registered decider.
     *
     * @param {HTMLElement} element
     *   The DOM element.
     * @param {BaseDecider} decider
     *   The decider.
     *
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    async _makeDecision(element, decider) {
      try {
        this.debug && console.debug('A/B Tests', 'A decision is about to be made.');
        const decision = await decider.decide(element);
        const status = 'success';
        decider.setStatus(status);
        // Set a data attribute to indicate the test is in progress.
        element.setAttribute('data-ab-tests-decision-status', status);
        this.debug && console.debug('A/B Tests', 'A decision was reached.', decision);
        await this._handleDecision(element, decision);
        return decision;
      }
      catch (error) {
        decider.onError(error);
        this.debug && console.error('A/B Tests', 'Decision failed:', error);
        // On error, show the default variant.
        this._showDefaultVariant(element);
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
     * @returns {Promise<Decision>}
     *   Resolves with the tracking made.
     */
    async _doTrack(element, { tracker, decision }) {
      try {
        this.debug && console.debug('A/B Tests', 'Tracking is about to start.');
        const result = await tracker.track(decision, element);
        const status = 'success';
        tracker.setStatus(status);
        element.setAttribute('data-ab-tests-tracking-status', status);
        this.debug && console.debug('A/B Tests', 'Tracking was successful.', result);
        return result;
      }
      catch (error) {
        tracker.onError(error);
        this.debug && console.error('A/B Tests', 'Tracking failed:', error);
      }
    }

    /**
     * Internal method to handle a decision once made.
     *
     * @param {HTMLElement} element
     *   The element.
     * @param {Decision} decision
     *   The decision object.
     */
    async _handleDecision(element, decision) {
      const uuid = element.getAttribute('data-ab-tests-entity-root');
      if (decision.displayMode === this.defaultViewMode) {
        this.debug && console.debug('A/B Tests', 'Un-hiding the default variant.');
        this._showDefaultVariant(element);
        this.debug && console.debug('A/B Tests', 'Default variant un-hidden.');
      }
      else {
        this.debug && console.debug('A/B Tests', 'Requesting node to be rendered via Ajax.', uuid, decision);
        await this._loadVariant(uuid, decision.displayMode);
        this.debug && console.debug('A/B Tests', 'The entity was rendered with the new view mode.', uuid, decision);
      }

      // Dispatch decision event.
      const event = new CustomEvent(this.eventName, {
        detail: {
          uuid,
          decision,
        },
        bubbles: true
      });
      this.debug && console.debug('A/B Tests', 'Dispatching event after showing the content.', event);
      element.dispatchEvent(event);
      this.debug && console.debug('A/B Tests', 'Event dispatched.', event);
    }

    /**
     * Internal method to show loading skeleton.
     *
     * @param {HTMLElement} element
     *   The root element to skeletonize.
     */
    _showSkeleton(element) {
      this.debug && console.debug('A/B Tests', 'Turining the default A/B Test view mode into the page skeleton');
      element.classList.add('ab-test-loading');
    }

    /**
     * Internal method to show the default variant.
     *
     * @param {HTMLElement} element
     *   The root element to show.
     */
    _showDefaultVariant(element) {
      element.classList.remove('ab-test-loading');
    }

    /**
     * Internal method to load a new variant via AJAX.
     *
     * @param {string} uuid
     *   The UUID of the entity.
     * @param {string} displayMode
     *   The display mode to load.
     * @returns {Promise}
     *   Resolves when the variant is loaded.
     */
    _loadVariant(uuid, displayMode) {
      return new Promise((resolve, reject) => {
        Drupal.ajax({
          url: `/ab-tests/render/${uuid}/${displayMode}`,
          submit: {
            uuid,
            display_mode: displayMode
          }
        }).execute()
          .then(resolve)
          .catch(reject);
      });
    }

 }

  // Make the singleton instance available globally.
  const debug = drupalSettings?.ab_tests?.debug || false;
  const defaultViewMode = drupalSettings?.ab_tests?.defaultViewMode || 'default';
  Drupal.abTests = new AbTests(debug, defaultViewMode);

  Drupal.behaviors.abTests = {
    attach: function (context, settings) {
      if (!Drupal.abTests) {
        console.warn('Drupal.abTests singleton is not available. Skipping A/B test processing.');
        return;
      }

      const elements = once(
        'ab-tests-element',
        '[data-ab-tests-entity-root]',
        context,
      );

      elements.forEach(Drupal.abTests.registerElement.bind(Drupal.abTests));
    },
  };

})(document, Drupal, drupalSettings);
