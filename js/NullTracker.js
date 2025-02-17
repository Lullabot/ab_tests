'use strict';

/**
 * Implements a null-pattern A/B test tracker.
 */
class NullTracker extends BaseTracker {

  /**
   * @inheritDoc
   */
  track(decision, element) {
    return Promise.reject({ decision, element });
  }

}
