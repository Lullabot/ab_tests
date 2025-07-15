/**
 * Implements a null-pattern A/B test decider.
 */
class NullDecider extends BaseDecider {
  /**
   * @inheritDoc
   */
  decide(element) {
    return Promise.reject();
  }
}
