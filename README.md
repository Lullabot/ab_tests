# A/B Tests

A flexible and extensible Drupal module for running A/B tests on your content.
This module empowers content teams to experiment with different content
presentations while collecting valuable user interaction data through a
sophisticated server-side rendering approach.

## What Does It Do?

The A/B Tests module enables you to:

- **View Mode Testing**: Present entire entities using different Drupal view
  modes
- **Block Testing**: A/B test individual Layout Builder blocks with different
  configurations
- **Analytics Integration**: Collect and analyze user interaction data through
  pluggable analytics systems
- **Custom Decision Logic**: Implement sophisticated variant selection
  algorithms
- **Server-Side Rendering**: Eliminate flash-of-original-content issues common
  with client-side tools

Thanks to its pluggable architecture, you can easily extend the module to:

- Implement custom variant decision logic (timeout-based, user-based, feature
  flags, etc.)
- Integrate with any analytics platform (Google Analytics, Adobe Analytics,
  custom systems)
- Add new tracking mechanisms and custom event collection
- Support different content types and rendering approaches

## Architecture

The A/B Tests module uses a sophisticated pluggable architecture built around
two core plugin types and two distinct testing approaches:

### Plugin System

#### Deciders

Deciders determine which variant to show to a user. They implement
`AbVariantDeciderInterface` and can consider factors such as:

- User session data
- Time-based rules
- Random distribution
- Custom business logic
- Feature-specific requirements (view modes vs blocks)

**Base Classes:**

- `AbVariantDeciderPluginBase`: Extends Drupal's `PluginBase` with configuration
  and form interfaces
- `TimeoutAbDeciderBase`: Abstract base for timeout-based random selection

#### Analytics/Trackers

Analytics plugins manage the reporting and tracking of test results. They
implement `AbAnalyticsInterface` and handle:

- Recording which variant was shown to users
- Tracking user interactions with variants
- Integration with external analytics platforms
- Custom event and metrics collection

**Base Classes:**

- `AbAnalyticsPluginBase`: Extends Drupal's `PluginBase` with UI and
  configuration support

### Testing Approaches

#### View Mode Testing

Tests entire entity rendering using different Drupal view modes:

- **Scope**: Complete entity presentation (node, user, etc.)
- **Configuration**: Set up at content type level via third-party settings
- **Rendering**: Server-side with Ajax re-rendering for variants
- **Use Cases**: Testing different content layouts, field arrangements, or
  styling approaches

#### Block Testing (Layout Builder Integration)

Tests individual blocks within Layout Builder with different configurations:

- **Scope**: Individual block instances within layouts
- **Configuration**: Per-block via Layout Builder interface
- **Rendering**: Client-side Ajax with sophisticated context preservation
- **Use Cases**: Testing block settings, display options, or conditional
  visibility

### Sub-modules

The module includes several sub-modules that demonstrate and extend
functionality:

#### ab_blocks

Enables A/B testing on Layout Builder blocks:

- Integrates with Layout Builder's component system
- Provides block-level configuration forms
- Handles context serialization for Ajax requests
- Location: `modules/ab_blocks/`

#### ab_analytics_tracker_example

Demonstrates custom analytics implementation:

- Provides `MockTracker` plugin example
- Shows analytics configuration patterns
- Includes JavaScript tracking component
- Location: `modules/ab_analytics_tracker_example/`

#### ab_variant_decider_view_mode_timeout

Time-based view mode variant selection:

- Extends `TimeoutAbDeciderBase`
- Provides view mode selection interface
- Demonstrates feature-restricted plugins
- Location: `modules/ab_variant_decider_view_mode_timeout/`

#### ab_variant_decider_block_timeout

Time-based block configuration variants:

- Similar to view mode timeout but for blocks
- Supports JSON-based block setting overrides
- Location: `modules/ab_variant_decider_block_timeout/`

### JavaScript Architecture

The module includes a sophisticated client-side component system:

- **BaseAction**: Common functionality for status tracking and error handling
- **BaseDecider/BaseTracker**: Abstract classes for plugin implementation
- **AbTestsManager**: Orchestrates deciders and trackers
- **DecisionHandlerFactory**: Creates appropriate handlers based on test type
- **ViewModeDecisionHandler/BlockDecisionHandler**: Feature-specific variant
  loading

## Configuration

### Ignoring Configuration Export

The module provides an option to ignore A/B test configurations during
configuration export. This is useful when you want to:

- Keep A/B test configurations out of version control
- Have different A/B test settings per environment
- Prevent A/B tests from being deployed/overritten across environments

To enable this feature, navigate to /admin/config/search/ab-tests and check the
box.

When enabled, any third-party settings from the A/B Tests module will be
excluded during configuration export and import.

## Usage

### Enabling View Mode Testing

To set up A/B testing for different view modes on a content type:

1. Navigate to **Structure → Content types → [Your content type] → Edit**
2. Scroll to the **A/B Tests** fieldset
3. Configure **Decider Plugin**:
  - Select a decider (e.g., "Timeout (View Mode)")
  - Configure plugin settings (timeout values, view modes to test)
4. Configure **Analytics Plugin** (optional):
  - Select an analytics tracker
  - Configure tracking settings
5. Save the content type configuration

View mode testing will now apply to all entities of this content type.

### Enabling Block Testing

To set up A/B testing for Layout Builder blocks:

1. Edit a page using Layout Builder
2. Add or configure an existing block
3. Look for the **A/B Testing** contextual link on the block
4. Configure the test:
  - Select a decider plugin (e.g., "Timeout (Block)")
  - Configure variant settings (block configuration overrides)
  - Set up analytics tracking
5. Save the layout

The block will now be A/B tested with the configured variants.

### Admin Settings

Global module settings are available at `/admin/config/search/ab-tests`:

- **Debug Mode**: Enable console logging for development
- **Configuration Export Control**: Exclude A/B test configs from export/import

## Implementation Guide

### Creating a Custom Decider Plugin

Decider plugins determine which variant to show users. They consist of both PHP
and JavaScript components that work together to make decisions and handle
variant loading.

#### 1. PHP Plugin Structure

Create your decider plugin by extending `AbVariantDeciderPluginBase`:

```php
<?php

namespace Drupal\your_module\Plugin\AbVariantDecider;

use Drupal\ab_tests\Plugin\AbVariantDecider\AbVariantDeciderPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @AbVariantDecider(
 *   id = "your_custom_decider",
 *   label = @Translation("Your Custom Decider"),
 *   description = @Translation("Description of your decision logic."),
 *   supported_features = {"ab_view_modes", "ab_blocks"},
 *   decider_library = "your_module/your_decider_js",
 * )
 */
class YourCustomDecider extends AbVariantDeciderPluginBase {

  public function defaultConfiguration() {
    return [
      'your_setting' => 'default_value',
    ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['your_setting'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Setting'),
      '#default_value' => $this->configuration['your_setting'],
    ];

    return $form;
  }

  // Override this method to provide settings passed to JavaScript
  protected function getJavaScriptSettings(): array {
    return [
      'yourSetting' => $this->configuration['your_setting'],
    ];
  }
}
```

**Key Plugin Annotation Properties:**

- `supported_features`: Array indicating which features this decider supports (
  `ab_view_modes`, `ab_blocks`, or both)
- `decider_library`: The Drupal library containing the JavaScript component

#### 2. JavaScript Component

Create the client-side decision logic by extending `BaseDecider`:

```javascript
// js/YourCustomDecider.js
'use strict';

/**
 * Custom decider implementation.
 */
class YourCustomDecider extends BaseDecider {

  /**
   * Constructor receives variants and config from PHP.
   *
   * @param {Array} variants
   *   Available variants (view modes or block settings).
   * @param {Object} config
   *   Configuration from PHP plugin.
   */
  constructor(variants, config) {
    super();
    this.variants = variants;
    this.yourSetting = config.yourSetting;
  }

  /**
   * Makes the decision about which variant to show.
   *
   * @param {HTMLElement} element
   *   The DOM element being tested.
   * @returns {Promise<Decision>}
   *   Promise that resolves to a Decision object.
   */
  decide(element) {
    return new Promise((resolve) => {
      // Your decision logic here
      // For view modes: return view mode string
      // For blocks: return JSON stringified block configuration
      const selectedVariant = this.selectVariant();
      const decisionId = this.generateDecisionId();

      resolve(new Decision(
        decisionId,
        selectedVariant,
        {
          yourMetadata: 'additional data',
          deciderId: 'your_custom_decider'
        }
      ));
    });
  }

  /**
   * Helper method to select a variant.
   */
  selectVariant() {
    // Implement your selection logic
    const randomIndex = Math.floor(Math.random() * this.variants.length);
    return this.variants[randomIndex];
  }
}
```

#### 3. Library Definition

Define the JavaScript library in your module's `.libraries.yml`:

```yaml
your_decider_js:
  js:
    js/YourCustomDecider.js: { }
  dependencies:
    - ab_tests/ab_tests_base
```

#### 4. Feature-Specific Considerations

**For View Mode Testing:**

- Variants are view mode machine names (e.g., 'teaser', 'full')
- The `decide()` method should return a view mode string
- Example: `timeoutVariantSettingsForm()` returns checkboxes for available view
  modes

**For Block Testing:**

- Variants are JSON-encoded block configuration objects
- The `decide()` method should return a JSON string of block settings
- Example: `'{"label_display":"0","hide_block":true}'`
- Block settings are merged with original configuration

### Creating a Custom Analytics Plugin

Analytics plugins handle tracking and reporting. Like deciders, they consist of
both PHP and JavaScript components.

#### 1. PHP Plugin Structure

```php
<?php

namespace Drupal\your_module\Plugin\AbAnalytics;

use Drupal\ab_tests\Plugin\AbAnalytics\AbAnalyticsPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @AbAnalytics(
 *   id = "your_custom_tracker",
 *   label = @Translation("Your Custom Tracker"),
 *   description = @Translation("Description of your tracking implementation."),
 *   tracker_library = "your_module/your_tracker_js",
 * )
 */
class YourCustomTracker extends AbAnalyticsPluginBase {

  public function defaultConfiguration() {
    return [
      'api_key' => '',
      'tracking_domain' => '',
    ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    $form['tracking_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tracking Domain'),
      '#default_value' => $this->configuration['tracking_domain'],
      '#required' => TRUE,
    ];

    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Add custom validation logic here
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['api_key'] = $form_state->getValue('api_key');
    $this->configuration['tracking_domain'] = $form_state->getValue('tracking_domain');
  }

  // Settings passed to JavaScript component
  protected function getJavaScriptSettings(): array {
    return [
      'apiKey' => $this->configuration['api_key'],
      'trackingDomain' => $this->configuration['tracking_domain'],
    ];
  }
}
```

#### 2. JavaScript Component

```javascript
// js/YourCustomTracker.js
'use strict';

/**
 * Custom analytics tracker implementation.
 */
class YourCustomTracker extends BaseTracker {

  /**
   * Constructor receives API key and config from PHP.
   *
   * @param {string} apiKey
   *   The API key for the tracking service.
   * @param {Object} config
   *   Configuration object from PHP.
   */
  constructor(apiKey, config) {
    super();
    this.apiKey = apiKey;
    this.trackingDomain = config.trackingDomain;
  }

  /**
   * Tracks an A/B test decision and user interactions.
   *
   * @param {Decision} decision
   *   The decision object containing variant information.
   * @param {HTMLElement} element
   *   The DOM element being tested.
   * @returns {Promise}
   *   Promise that resolves when tracking is complete.
   */
  track(decision, element) {
    this.getDebug() && console.debug('[A/B Tests]', 'Starting tracking:', decision);

    return new Promise((resolve, reject) => {
      // Track the initial decision
      this.trackDecision(decision)
        .then(() => {
          // Set up interaction tracking
          this.setupInteractionTracking(element, decision);
          resolve();
        })
        .catch(reject);
    });
  }

  /**
   * Tracks the A/B test decision.
   */
  async trackDecision(decision) {
    const payload = {
      test_id: decision.decisionId,
      variant: decision.decisionValue,
      metadata: decision.decisionData,
      timestamp: Date.now()
    };

    try {
      await fetch(`https://${this.trackingDomain}/track`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${this.apiKey}`
        },
        body: JSON.stringify(payload)
      });
    } catch (error) {
      console.error('[A/B Tests] Tracking failed:', error);
    }
  }

  /**
   * Sets up tracking for user interactions with the tested element.
   */
  setupInteractionTracking(element, decision) {
    // Track clicks
    element.addEventListener('click', (event) => {
      this.trackInteraction('click', decision, {
        target: event.target.tagName,
        coordinates: { x: event.clientX, y: event.clientY }
      });
    });

    // Track form submissions if present
    const forms = element.querySelectorAll('form');
    forms.forEach(form => {
      form.addEventListener('submit', () => {
        this.trackInteraction('form_submit', decision, {
          form_id: form.id || 'anonymous'
        });
      });
    });
  }

  /**
   * Tracks a specific user interaction.
   */
  async trackInteraction(eventType, decision, eventData) {
    const payload = {
      test_id: decision.decisionId,
      event_type: eventType,
      event_data: eventData,
      timestamp: Date.now()
    };

    try {
      await fetch(`https://${this.trackingDomain}/interaction`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${this.apiKey}`
        },
        body: JSON.stringify(payload)
      });
    } catch (error) {
      console.error('[A/B Tests] Interaction tracking failed:', error);
    }
  }
}
```

#### 3. Library Definition

```yaml
your_tracker_js:
  js:
    js/YourCustomTracker.js: { }
  dependencies:
    - ab_tests/ab_tests_base
```

### Plugin Best Practices

#### For Deciders:

- Implement deterministic logic when possible
- Handle edge cases gracefully
- Validate configuration thoroughly
- Use appropriate randomization for statistical validity
- Consider performance implications of decision logic

#### For Analytics:

- Implement robust error handling
- Respect user privacy and consent
- Handle network failures gracefully
- Provide clear configuration validation
- Document data collection practices

## Benefits Over External A/B Testing Tools

The A/B Tests module provides significant advantages over external tools like
Optimizely, VWO, or Google Optimize:

### Server-Side Rendering Benefits

**No Flash of Original Content (FOOC)**

- External tools manipulate the DOM after page load, causing visible content
  shifts
- This module renders variants server-side, eliminating visual flicker

**SEO-Friendly Testing**

- Search engines see properly rendered variant content
- No JavaScript-dependent content manipulation
- Proper cache headers and metadata for all variants

**Performance Advantages**

- No additional JavaScript libraries from external vendors
- Variants are rendered as part of Drupal's normal render pipeline
- Leverages Drupal's caching system for optimal performance

### Drupal Integration Benefits

**Native Cache Integration**

- Proper Drupal cache contexts and metadata
- Variant content cached efficiently
- Cache invalidation works correctly with test changes

**Access Control Compliance**

- Respects Drupal permissions and access controls
- Content variants follow the same security model
- No bypass of Drupal's access system

**Context Awareness**

- Full access to Drupal entities, user context, and routing information
- Block testing preserves Layout Builder context across Ajax requests
- Proper integration with Drupal's render pipeline

### Development and Maintenance

**Type Safety and Standards**

- Strong PHP typing and interfaces
- Follows Drupal coding standards
- Plugin architecture allows extension without core modifications

**Configuration Management**

- Optional exclusion from config export for environment-specific testing
- Integrates with Drupal's configuration system
- Version control friendly

**Debugging and Development**

- Built-in debug mode with console logging
- Clear error handling and fallback mechanisms
- Development tools integration

### Data Privacy and Compliance

**Self-Hosted Solution**

- No third-party data sharing required
- Complete control over user data
- GDPR and privacy compliance under your control

**Custom Analytics Integration**

- Pluggable analytics system allows custom compliance implementations
- Choose your own analytics platforms
- Control exactly what data is collected and how

### Cost and Vendor Independence

**No External Dependencies**

- No subscription costs for external A/B testing platforms
- No vendor lock-in
- Complete control over testing infrastructure

**Scalability**

- Scales with your Drupal infrastructure
- No per-test or per-visitor charges
- Unlimited tests and variants within your hosting capacity

## Contributing

We welcome contributions in the form of:

- Bug fixes.
- Documentation improvements.

Please follow
the [Drupal coding standards](https://www.drupal.org/docs/develop/standards)
when submitting your contributions.

## Maintainers

This module is maintained by the Lullabot team. For support, please open an
issue in the project's issue queue.
