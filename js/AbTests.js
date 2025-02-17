((Drupal, drupalSettings) => {
  'use strict';

  /**
   * Manages A/B test decisions and variant switching.
   */
  class AbTests {
    constructor() {
      if (AbTests.instance) {
        return AbTests.instance;
      }

      this.decisions = new Map();
      this.elements = new Map();
      this.deciders = new Map();
      this.trackers = new Map();
      this.debug = drupalSettings?.ab_tests?.debug || false;
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
      const uuid = element.getAttribute('data-ab-tests-entity-root');
      if (!uuid) {
        this.debug && console.warn('A/B Tests', 'Element missing data-ab-tests-entity-root attribute:', element);
        return this;
      }
      // Only register elements once.
      if (this.elements.get(uuid)) {
        return this;
      }
      this.elements.set(uuid, element);

      // Hide the default variant and show skeleton.
      this._showSkeleton(element);

      return this;
    }

    /**
     * Registers a decider for a specific test.
     *
     * @param {string} uuid
     *   The UUID of the A/B test.
     * @param {BaseDecider} decider
     *   The decider instance that implements decide().
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    registerDecider(uuid, decider) {
      decider.setStatus('pending');
      this.deciders.set(uuid, decider);

      // Start the decision process.
      return this._makeDecision(uuid);
    }

    /**
     * Registers a tracker for a specific test.
     *
     * @param {string} uuid
     *   The UUID of the A/B test.
     * @param {BaseTracker} tracker
     *   The tracker instance that implements track().
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    registerTracker(uuid, tracker) {
      tracker.setStatus('pending');
      this.trackers.set(uuid, tracker);

      // Start the tracking process.
      return this._doTrack(uuid);
    }

    /**
     * Internal method to make a decision using the registered decider.
     *
     * @param {string} uuid
     *   The UUID of the A/B test.
     * @returns {Promise<Decision>}
     *   Resolves with the decision once made.
     */
    async _makeDecision(uuid) {
      const element = this.elements.get(uuid);
      const decider = this.deciders.get(uuid);

      if (!element || !decider) {
        return Promise.reject(new Error('Missing element or decider for UUID: ' + uuid));
      }

      try {
        this.debug && console.debug('A/B Tests', 'A decision is about to be made.');
        const decision = await decider.decide();
        decider.setStatus('success');
        this.debug && console.debug('A/B Tests', 'A decision was reached.', decision);
        await this._handleDecision(uuid, decision);
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
     * @param {string} uuid
     *   The UUID of the A/B test.
     * @returns {Promise<Decision>}
     *   Resolves with the tracking made.
     */
    async _doTrack(uuid) {
      const element = this.elements.get(uuid);
      const tracker = this.trackers.get(uuid);

      if (!element || !decider) {
        return Promise.reject(new Error('Missing element or tracker for UUID: ' + uuid));
      }

      try {
        this.debug && console.debug('A/B Tests', 'Tracking is about to start.');
        const result = await tracker.track();
        tracker.setStatus('success');
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
     * @param {string} uuid
     *   The UUID of the A/B test.
     * @param {Decision} decision
     *   The decision object.
     */
    async _handleDecision(uuid, decision) {
      const element = this.elements.get(uuid);
      this.decisions.set(uuid, decision);

      // @todo Check the actual defautl from the content type settings.
      if (decision.displayMode === 'default') {
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
      const event = new CustomEvent('abTestFinished', {
        detail: {
          uuid,
          decision,
          element
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
  Drupal.abTests = new AbTests();
})(Drupal, drupalSettings);
