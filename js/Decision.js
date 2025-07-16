/**
 * Represents an A/B test decision.
 */
class Decision {
  /**
   * Constructs a new Decision instance.
   *
   * @param {string} decisionId
   *   Unique identifier for this decision.
   * @param {string} decisionValue
   *   The decision. IMPORTANT: Decisions are strings, or they will be
   *   serialized to one.
   * @param {Object} decisionData
   *   Additional metadata about the decision.
   */
  constructor(decisionId, decisionValue, decisionData = {}) {
    this.decisionId = decisionId;
    this.decisionValue = `${decisionValue}`;
    this.decisionData = decisionData;
  }
}
