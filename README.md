# A/B Tests

A flexible and extensible Drupal module for running A/B tests on your content types. This module empowers content teams to experiment with different content presentations while collecting valuable user interaction data.

## What Does It Do?

The A/B Tests module enables you to:
- Present content using different view modes.
- Configure testing parameters for each content type.
- Collect and analyze user interaction data.
- Make data-driven decisions about content presentation.

Thanks to its pluggable architecture, you can easily extend the module to:
- Implement custom variant decision logic.
- Integrate with your preferred analytics platform.
- Add new tracking mechanisms.

## Architecture

The module is built around two core concepts:

### Deciders
Deciders determine which variant (view mode) to show to a user. They can consider factors such as:
- User session data.
- Time-based rules.
- Random distribution.
- Custom business logic.

### Trackers
Trackers manage the reporting of:
- Which variant was shown to a user.
- How users interact with each variant.
- Custom events and metrics.

## Creating a Custom Decider

To create a custom decider, implement `\Drupal\ab_tests\Plugin\AbVariantDecider\AbVariantDeciderInterface`. The `ab_variant_decider_timeout` module provides a practical example:

1. Create a new plugin class in `src/Plugin/AbVariantDecider`
2. Add the plugin annotation
3. Implement the required methods

A well-designed decider should have:
- Clear decision logic.
- Configurable parameters.
- Proper session handling.
- Documentation of the decision process.

## Creating a Custom Tracker

To create a custom tracker, implement `\Drupal\ab_tests\Plugin\AbTestTracker\AbTestTrackerInterface`. See the `ab_analytics_tracker_example` module for a comprehensive example:

1. Create a new plugin class in `src/Plugin/AbTestTracker`
2. Add the plugin annotation
3. Implement the required methods

A well-designed tracker should:
- Handle tracking initialization cleanly.
- Provide clear event reporting.
- Include proper error handling.
- Document the tracking implementation.

## Contributing

We welcome contributions in the form of:
- Bug fixes.
- Documentation improvements.

Please follow the [Drupal coding standards](https://www.drupal.org/docs/develop/standards) when submitting your contributions.

## Maintainers

This module is maintained by the Lullabot team. For support, please open an issue in the project's issue queue.
