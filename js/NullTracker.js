/**
 * Implements a null-pattern A/B test tracker.
 */
class NullTracker extends BaseTracker {
  /**
   * @inheritDoc
   */
  async track(trackingInfo, element) {
    return Promise.resolve(new Error('NullTracker: No tracking configured'));
  }
}
