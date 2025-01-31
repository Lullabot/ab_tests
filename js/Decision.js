'use strict';

/**
 * Represents an A/B test decision.
 */
class Decision {
  /**
   * Constructs a new Decision instance.
   *
   * @param {string} decisionId
   *   Unique identifier for this decision.
   * @param {string} displayMode
   *   The selected display mode variant.
   * @param {Object} decisionData
   *   Additional metadata about the decision.
   */
  constructor(decisionId, displayMode, decisionData = {}) {
    this.decisionId = decisionId;
    this.displayMode = displayMode;
    this.decisionData = decisionData;
  }
}
