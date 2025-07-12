((document, Drupal, drupalSettings) => {
  'use strict';

  /**
   * Show loading skeleton.
   */
  const showLoadingSkeleton = (debug) => (element) => {
    debug && console.debug('[A/B Tests]', 'Turning the default A/B Test view mode into the page skeleton');
    element.classList.add('ab-test-loading');
  }

  Drupal.behaviors.abTests = {
    attach: function (context, { ab_tests: { debug } }) {
      const elements = once(
        'ab-tests-element',
        // All the A/B tests should render from the server side with this data
        // attribute.
        '[data-ab-tests-decider-status="idle"]',
        context,
      );
      elements.forEach(showLoadingSkeleton(debug));
    },
  };

})(document, Drupal, drupalSettings);
