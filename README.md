# A/B Tests

A Drupal module that provides a flexible framework for implementing A/B testing on
your site. It allows content editors to create different versions of content and
test them against each other using various decision strategies.

## Summary

The A/B Tests module enables content teams to:
- Create multiple display variants of content using Drupal's view modes
- Test these variants against each other using configurable decision strategies
- Implement custom decision strategies through a plugin system
- Handle variant switching gracefully with loading states
- Collect and analyze test results (through custom decider implementations)

## Installation

You can install this module using Composer:

```bash
composer require drupal/ab_tests
```
Then enable it using Drush:

```bash
drush en ab_tests
```

## How it Works

The A/B Tests module provides a framework for testing different versions of your
content. Here's how it works:

1. **Content Type Configuration**: Enable A/B testing for specific content types
   and configure which view modes can be used as variants.

2. **Variant Creation**: Use Drupal's view modes to create different versions of
   how your content should be displayed.

3. **Decision Making**: The module uses a plugin system called "deciders" that
   determine which variant a user should see. The module comes with:
   - A Null decider (for testing)
   - A Timeout decider (for demonstration)

4. **Frontend Experience**: When a user visits a page:
   - The default variant is loaded but hidden
   - A loading state is shown while the decision is being made
   - Once decided, the chosen variant is displayed
   - If any errors occur, the default variant is shown

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

## Tracking & Analytics

The A/B Tests module focuses on variant delivery and does not include built-in
analytics tracking. This is by design, as different projects have different
analytics needs and implementations. To track the success of your A/B tests:

1. **Implementation**: Use your project's existing analytics tools (Google
   Analytics, Adobe Analytics, etc.) to track variant performance. Common
   approaches include:
   - Custom events/dimensions for variant identification
   - Goal conversion tracking per variant
   - User engagement metrics

2. **Decision Making**: Base your variant decisions on your analytics data. This
   typically involves:
   - Setting up custom reports/dashboards
   - Defining success metrics (conversions, engagement, etc.)
   - Statistical analysis of variant performance

Example using Google Analytics 4:
```js
// Track which variant was shown
gtag('event', 'variant_view', {
  'ab_test_id': 'homepage_hero',
  'variant': 'variant_b',
  'decider': 'cookie'
});

// Track conversion events with variant data
gtag('event', 'conversion_event', {
  'ab_test_id': 'homepage_hero',
  'variant': 'variant_b',
  'conversion_type': 'signup'
});
```

## Contributing

We love contributions! Please read our [contributing guidelines](CONTRIBUTING.md)
and submit pull requests to [our GitHub repository](https://github.com/Lullabot/ab_tests).
