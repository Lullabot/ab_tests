'use strict';

/**
 * Implements a timeout-based A/B test decider.
 */
class MockTracker extends BaseTracker {

  /**
   * Constructs a new TimeoutDecider instance.
   *
   * @param {string} apiKey
   *   The tracking service apiKey.
   * @param {Object} config
   *   Configuration object.
   * @param {string} config.trackingDomain
   *   The tracking domain.
   */
  constructor(apiKey, config) {
    super();
    this.apiKey = apiKey;
    this.trackingDomain = config.trackingDomain;
  }

  /**
   * @inheritDoc
   */
  track(decision, element) {
    this.getDebug() && console.debug('[A/B Tests]', 'MockTracker: starting tracking:', decision, this.apiKey, this.trackingDomain);
    return new Promise((resolve) => {
      // First simulate tracking the decision.
      setTimeout(() => {
        console.log('[A/B Tests]', 'MockTracker: Event tracked successfully:', decision, this.apiKey, this.trackingDomain);
        resolve();
      }, 500);
      // Then simulate tracking some UX events.
      element.addEventListener('click', (event) => {
        event.target.classList.add('clicked');
        console.log(
          'A/B Tests',
          'MockTracker: Event clicked:',
          element,
          decision,
          this.apiKey,
          this.trackingDomain,
        );
      });
    });
  }
}
