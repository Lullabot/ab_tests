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
   * @return {Promise<Decision>}
   *   Resolves with the decision.
   *
   * @throws {Error}
   *   When there is an error.
   * @abstract
   */
  decide(element) {
    throw new Error('Decider must implement decide() method.');
  }

  /**
   * Generates a unique decision ID.
   *
   * @return {string}
   *   A unique identifier for this decision.
   */
  generateDecisionId() {
    return `decision-${Math.random().toString(36).slice(2, 11)}`;
  }
}
