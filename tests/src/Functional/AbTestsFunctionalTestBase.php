<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\Functional;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\ab_tests\AbTestsTestBaseTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides a base class for AB Tests functional tests.
 */
abstract class AbTestsFunctionalTestBase extends BrowserTestBase {

  use AbTestsTestBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'ab_tests',
    'ab_analytics_tracker_example',
    'ab_variant_decider_timeout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user with all the necessary permissions.
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    // Create a test content type.
    $this->contentType = $this->drupalCreateContentType([
      'type' => 'ab_test_type',
      'name' => 'A/B Test Type',
    ]);
  }


  /**
   * Asserts that certain form elements exist on the content type form.
   *
   * @param array $expected_elements
   *   Array of element IDs to check for.
   */
  protected function assertContentTypeFormElements(array $expected_elements): void {
    foreach ($expected_elements as $element_id) {
      $this->assertSession()->fieldExists($element_id);
    }
  }

  /**
   * Asserts that certain third-party settings exist for a content type.
   *
   * @param string $type_id
   *   The content type machine name.
   * @param array $expected_settings
   *   The expected settings array.
   */
  protected function assertContentTypeThirdPartySettings(array $expected_settings): void {
    $saved_settings = $this->contentType->getThirdPartySetting('ab_tests', 'ab_tests', []);

    foreach ($expected_settings as $key => $value) {
      $this->assertEquals(
        $value,
        $saved_settings[$key] ?? NULL,
        sprintf('The third-party setting %s was not saved correctly.', $key)
      );
    }
  }

}
