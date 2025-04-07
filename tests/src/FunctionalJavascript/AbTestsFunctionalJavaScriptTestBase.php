<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\FunctionalJavascript;

use Drupal\Core\Session\AccountInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\ab_tests\AbTestsTestBaseTrait;

/**
 * Provides a base class for AB Tests JavaScript functional tests.
 */
abstract class AbTestsFunctionalJavaScriptTestBase extends WebDriverTestBase {

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
   * Asserts that a form field exists with specific label.
   *
   * @param string $field_selector
   *   The field selector.
   * @param string $label
   *   The expected label.
   */
  protected function assertFieldWithLabel(string $field_selector, string $label): void {
    $field = $this->getSession()->getPage()->find('css', $field_selector);
    $this->assertNotNull($field, "Field with selector '$field_selector' not found.");

    $field_id = $field->getAttribute('id');
    $label_element = $this->getSession()->getPage()->find('css', "label[for='$field_id']");
    $this->assertNotNull($label_element, "Label for field with ID '$field_id' not found.");
    $this->assertEquals($label, $label_element->getText(), "Label for field with ID '$field_id' does not match expected value.");
  }

  /**
   * Asserts that a specific value exists in drupalSettings.
   *
   * @param string $key
   *   The drupalSettings key.
   * @param mixed $expected_value
   *   The expected value.
   * @param string $message
   *   (optional) Message to show if assertion fails.
   */
  protected function assertDrupalSetting(string $key, $expected_value, string $message = ''): void {
    // Get the actual value using JavaScript.
    $actual_value = $this->getSession()->evaluateScript("return drupalSettings.$key");
    $this->assertEquals(
      $expected_value,
      $actual_value,
      $message ?: "The value of drupalSettings.$key does not match the expected value."
    );
  }

  /**
   * Asserts that certain content type form elements exist.
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
  protected function assertContentTypeThirdPartySettings(string $type_id, array $expected_settings): void {
    $content_type = NodeType::load($type_id);
    $saved_settings = $content_type->getThirdPartySetting('ab_tests', 'ab_tests', []);

    foreach ($expected_settings as $key => $value) {
      $this->assertEquals(
        $value,
        $saved_settings[$key] ?? NULL,
        sprintf('The third-party setting %s was not saved correctly.', $key)
      );
    }
  }

  /**
   * Creates a random node.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createRandomNode(): NodeInterface {
    return $this->drupalCreateNode(['type' => $this->contentType->id()]);
  }

}
