/**
 * Implements a null-pattern A/B test tracker.
 */
class NullTracker extends BaseTracker {
  /**
   * @inheritDoc
   */
  track(decision, element) {
    return Promise.reject(new Error('NullTracker: No tracking configured'));
  }
}
