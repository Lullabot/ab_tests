'use strict';

/**
 * Base class for A/B test deciders.
 *
 * @abstract
 */
class BaseDecider extends BaseAction {

  /**
   * Makes a decision about which variant to display.
   *
   * @param {HTMLElement} element
   *   The element to decide on.
   *
   * @returns {Promise<Decision>}
   *   Resolves with the decision.
   */
  decide(element) {
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

}
