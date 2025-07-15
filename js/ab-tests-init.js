((document, Drupal, drupalSettings) => {
  /**
   * Show loading skeleton.
   *
   * @param {boolean} debug
   *   Whether debug mode is enabled.
   *
   * @return {Function}
   *   Function that accepts an element to show loading skeleton on.
   */
  const showLoadingSkeleton = debug => element => {
    debug &&
      console.debug(
        '[A/B Tests]',
        'Turning the default A/B Test view mode into the page skeleton',
      );
    element.classList.add('ab-test-loading');
  };

  Drupal.behaviors.abTests = {
    attach(context, { ab_tests: { debug } }) {
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
