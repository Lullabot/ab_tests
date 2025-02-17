# A/B Tests

A Drupal module that helps you run A/B tests by switching between different display modes of your content. Perfect for testing different layouts and designs with built-in analytics tracking!

## What does it do?

The A/B Tests module allows content editors to create A/B tests using Drupal's built-in display modes. Instead of maintaining separate branches of code or complex feature flags, you can:

- Configure different display modes for your content types
- Enable A/B testing per content type
- Use different "decider" plugins to control how variants are selected
- Track test results using configurable analytics trackers
- Seamlessly switch between variants using JavaScript
- Fall back gracefully to a default variant if something goes wrong

## Installation

You can install this module using Composer:

```bash
composer require lullabot/ab_tests
```

Then enable it using Drush:

```bash
drush en ab_tests
```

## How it works

1. **Configure your content type**: Visit the content type edit form and enable A/B testing in the "A/B Tests" vertical tab.

2. **Set up display modes**: Create and configure different display modes for your content type. Each display mode represents a variant in your A/B test.

3. **Choose a decider**: Select which decider plugin will determine which variant to show to your users. The module comes with:
   - A Null decider (always shows the default variant)
   - A Timeout decider (randomly selects a variant after a configurable delay)

4. **Configure analytics**: Select an analytics tracker to record test results. Analytics trackers can:
   - Send data to various analytics platforms (Google Analytics, Adobe Analytics, etc.)
   - Track which variants are shown to users
   - Record conversion events and success metrics
   - Help you make data-driven decisions about which variants perform better

5. **Create content**: Create content as usual. The module will automatically handle:
   - Showing different variants to different users based on your configuration
   - Tracking variant performance through your chosen analytics tracker
   - Falling back to the default variant if any errors occur

The module uses progressive enhancement to switch variants:

1. The default variant is rendered server-side
2. JavaScript initializes the A/B test
3. The decider plugin makes a decision
4. If successful, the chosen variant is loaded via AJAX
5. The analytics tracker records the variant display
6. If anything fails, the user continues seeing the default variant

## Creating a Custom Decider

Let's walk through creating a custom decider. We'll use the Timeout decider as an
example and create a "Cookie" decider that makes decisions based on a cookie value.

1. First, create a new module called `ab_variant_decider_cookie` with this
   structure:

```
ab_variant_decider_cookie/
├── ab_variant_decider_cookie.info.yml
├── ab_variant_decider_cookie.libraries.yml
├── js/
│ ├── CookieDecider.js
│ └── ab-variant-decider-cookie.js
└── src/
    └── Plugin/
        └── AbVariantDecider/
            └── CookieAbDecider.php
```

2. Create the `.info.yml` file:

```yaml
name: Cookie A/B Variant Decider
description: Makes A/B test decisions based on cookie values.
core_version_requirement: ^10 || ^11
type: module
dependencies:
  - ab_tests
package: A/B Testing
```

3. Create the plugin class in `CookieAbDecider.php`:

```php
<?php

namespace Drupal\ab_variant_decider_cookie\Plugin\AbVariantDecider;

use Drupal\ab_tests\AbVariantDeciderPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @AbVariantDecider(
 *   id = "cookie",
 *   label = @Translation("Cookie"),
 *   description = @Translation("Makes decisions based on cookie values."),
 *   decider_library = "ab_variant_decider_cookie/ab_variant_decider.cookie"
 * )
 */
class CookieAbDecider extends AbVariantDeciderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'cookie_name' => 'ab_variant',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [
      'cookie_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Cookie Name'),
        '#description' => $this->t('The name of the cookie to check for the variant.'),
        '#default_value' => $this->configuration['cookie_name'],
        '#required' => TRUE,
      ],
    ];
  }

}
```

4. Create the JavaScript decider in `CookieDecider.js`:

```js
'use strict';

/**
 * CookieDecider class for making A/B test variant decisions based on cookie
 * values. Extends the BaseDecider class to provide cookie-based decision
 * functionality.
 */
class CookieDecider extends BaseDecider {
  /**
   * Creates a new CookieDecider instance.
   * @param {boolean} debug - Whether debug mode is enabled
   */
  constructor(debug) {
    super(debug);
  }

  /**
   * Makes a decision about which variant to show based on a cookie value.
   * @returns {Promise<Decision>} A promise that resolves to a Decision object
   */
  decide() {
    return new Promise((resolve) => {
      // Get the value of the ab_variant cookie
      const cookieValue = this.getCookie('ab_variant');

      // Create and resolve a new Decision object
      // If no cookie value exists, fall back to 'default' variant
      resolve(new Decision(
        this.generateDecisionId(),
        cookieValue || 'default',
        {
          deciderId: 'cookie',
          cookieValue
        }
      ));
    });
  }

  /**
   * Retrieves the value of a cookie by name.
   * @param {string} name - The name of the cookie to retrieve
   * @returns {string|null} The cookie value if found, null otherwise
   */
  getCookie(name) {
    // Get all cookies as a string
    const value = `${document.cookie}`;
    // Split cookies string on the target cookie name
    const parts = value.split(`${name}=`);
    // If we found the cookie (split resulted in 2 parts)
    if (parts.length === 2) {
      // Get everything after the cookie name, then get the value before the next
      // semicolon
      return parts.pop().split(';').shift();
    }
    return null;
  }
}
```

5. Create the behavior in `ab-variant-decider-cookie.js`:

```js
((Drupal, once) => {
  'use strict';

  /**
   * Behavior for the Cookie A/B test decider.
   */
  Drupal.behaviors.abVariantDeciderCookie = {
    /**
     * Attaches the Cookie decider behavior.
     *
     * @param {Element} context
     *   The DOM element to attach to.
     * @param {object} settings
     *   Drupal settings object.
     */
    attach(context, settings) {
      // Return early if abTests is not available
      if (!Drupal.abTests) {
        return;
      }

      // Find all root elements that haven't been processed yet
      const elements = once(
        'ab-variant-decider-cookie',
        '[data-ab-tests-entity-root]',
        context,
      );

      // Attach a new Cookie decider to each element
      elements.forEach(
        element => {
          const decider = new CookieDecider(settings.debug);
          decider.attach(element);
        }
      );
    }
  };
})(Drupal, once);
```

## Creating a Custom Analytics Tracker

You can create custom analytics trackers to integrate with your preferred analytics platform. Here's an example that tracks variants in Google Analytics 4:

1. Create a new plugin class:

```php
namespace Drupal\my_module\Plugin\AbAnalytics;

use Drupal\ab_tests\AbAnalyticsPluginBase;

/**
 * @AbAnalytics(
 *   id = "ga4",
 *   label = @Translation("Google Analytics 4"),
 *   description = @Translation("Tracks A/B test variants using GA4 events.")
 * )
 */
class Ga4Analytics extends AbAnalyticsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    return [
      '#attached' => [
        'library' => ['my_module/ab_analytics.ga4'],
        'drupalSettings' => [
          'abTests' => [
            'analytics' => [
              'ga4' => [
                'measurementId' => $this->configuration['measurement_id'],
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
```

2. Create the JavaScript tracker:

```javascript
((Drupal) => {
  'use strict';

  Drupal.behaviors.abAnalyticsGa4 = {
    attach(context, settings) {
      once('ab-analytics-ga4', '[data-ab-tests-entity-root]', context)
        .forEach(element => {
          // Track which variant was shown
          gtag('event', 'variant_view', {
            'ab_test_id': element.dataset.abTestsEntityRoot,
            'variant': element.dataset.abTestsVariant,
          });
        });
    }
  };
})(Drupal);
```

## Contributing

We love contributions! Please read our [contributing guidelines](CONTRIBUTING.md) and submit pull requests to [our GitHub repository](https://github.com/Lullabot/ab_tests).
