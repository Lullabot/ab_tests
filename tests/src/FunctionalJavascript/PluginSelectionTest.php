<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\FunctionalJavascript;

use Drupal\node\Entity\NodeType;

/**
 * Tests the plugin selection functionality.
 *
 * @group ab_tests
 */
class PluginSelectionTest extends AbTestsFunctionalJavaScriptTestBase {

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
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Creates a content type programmatically.
   *
   * @param string $type_id
   *   The content type machine name.
   * @param string $name
   *   The content type human-readable name.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The created content type.
   */
  protected function createTestContentType(string $type_id, string $name): NodeType {
    $content_type = NodeType::create([
      'type' => $type_id,
      'name' => $name,
    ]);
    $content_type->save();

    // Create a node form display for this content type.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', $type_id, 'default')
      ->save();

    // Create a node view display for this content type.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', $type_id, 'default')
      ->save();

    // Create a teaser view display for this content type.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', $type_id, 'teaser')
      ->save();

    return $content_type;
  }

  /**
   * Tests the AJAX behavior when switching variant decider plugins.
   */
  public function testPluginSelection(): void {
    // Access the content type edit form.
    $this->drupalGet('admin/structure/types/manage/' . $this->contentType->id());

    // Enable A/B testing.
    $page = $this->getSession()->getPage();
    $page->clickLink('A/B Tests');
    $page->checkField('ab_tests[is_active]');

    // Change to the timeout plugin.
    $page->selectFieldOption('ab_tests[variants][id]', 'timeout');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // The timeout plugin configuration form should now be shown.
    $this->assertSession()->elementTextEquals('css', '[data-drupal-selector="edit-ab-tests-variants-config-wrapper-settings"] > legend', 'Timeout');
    // Confirm that the timeout plugin form includes specific fields.
    $this->assertSession()->elementExists('css', 'input[name="ab_tests[variants][config_wrapper][settings][timeout][min]"]');
    $this->assertSession()->elementExists('css', 'input[name="ab_tests[variants][config_wrapper][settings][timeout][max]"]');

    $page->selectFieldOption('ab_tests[variants][id]', 'null');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // The timeout plugin configuration form should not be shown.
    $this->assertSession()->elementNotExists('css', '#edit-ab-tests-variants-config-wrapper-settings');

    // Select the mock tracker plugin (now using checkboxes).
    $page->checkField('ab_tests[analytics][id][mock_tracker]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // The mock tracker plugin configuration form should now be shown.
    $this->assertSession()->elementTextEquals('css', '[data-drupal-selector="edit-ab-tests-analytics-config-wrapper-mock-tracker-settings"] > legend', 'Mock Tracker');
    // Confirm that the mock tracker plugin form includes specific fields.
    $this->assertSession()->elementExists('css', 'input[name="ab_tests[analytics][config_wrapper][mock_tracker_settings][api_key]"]');
    $this->assertSession()->elementExists('css', 'input[name="ab_tests[analytics][config_wrapper][mock_tracker_settings][tracking_domain]"]');

    // Uncheck the mock tracker plugin.
    $page->uncheckField('ab_tests[analytics][id][mock_tracker]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // The mock tracker plugin configuration form should not be shown.
    $this->assertSession()->elementNotExists('css', '#edit-ab-tests-analytics-config-wrapper-mock-tracker-settings');
    
    // Test multiple tracker selection.
    $page->checkField('ab_tests[analytics][id][mock_tracker]');
    $page->checkField('ab_tests[analytics][id][null]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Both configuration forms should be shown.
    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-ab-tests-analytics-config-wrapper-mock-tracker-settings"]');
    // Null tracker should not have configuration form.
    $this->assertSession()->elementNotExists('css', '[data-drupal-selector="edit-ab-tests-analytics-config-wrapper-null-settings"]');
  }

  /**
   * Tests complex configuration using TimeoutAbDecider.
   */
  public function testPluginConfiguration(): void {
    // Access the content type edit form.
    $this->drupalGet('admin/structure/types/manage/' . $this->contentType->id());

    // Enable A/B testing.
    $page = $this->getSession()->getPage();
    $page->clickLink('A/B Tests');
    $page->checkField('ab_tests[is_active]');

    // Fill in the default display.
    $page->fillField('ab_tests[default][display_mode]', 'node.full');

    // Change to the timeout plugin.
    $page->selectFieldOption('ab_tests[variants][id]', 'timeout');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Fill in the timeout configuration.
    $page->fillField('ab_tests[variants][config_wrapper][settings][timeout][min]', '500');
    $page->fillField('ab_tests[variants][config_wrapper][settings][timeout][max]', '2000');
    // Select available variants.
    $page->checkField('ab_tests[variants][config_wrapper][settings][available_variants][rss]');
    $page->checkField('ab_tests[variants][config_wrapper][settings][available_variants][teaser]');

    // Select the mock tracker plugin (now using checkboxes).
    $page->checkField('ab_tests[analytics][id][mock_tracker]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Fill in the mock tracker configuration.
    $page->fillField('ab_tests[analytics][config_wrapper][mock_tracker_settings][api_key]', 'test-api-key-123');
    $page->fillField('ab_tests[analytics][config_wrapper][mock_tracker_settings][tracking_domain]', 'custom.track.example.com');

    // Save the form.
    $page->pressButton('Save');

    $this->assertSession()->pageTextContains('The content type A/B Test Type has been updated.');

    // Verify the settings were saved.
    $content_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($this->contentType->id());
    $saved_settings = $content_type->getThirdPartySetting('ab_tests', 'ab_tests');

    $this->assertEquals('timeout', $saved_settings['variants']['id']);
    $this->assertEquals(['min' => 500, 'max' => 2000], $saved_settings['variants']['settings']['timeout']);
    $this->assertEquals(['rss', 'teaser'], array_filter(array_values($saved_settings['variants']['settings']['available_variants'])));
    // Analytics plugins are now stored in a multi-plugin format.
    $this->assertEquals('mock_tracker', $saved_settings['analytics']['mock_tracker']['id']);
    $this->assertEquals('test-api-key-123', $saved_settings['analytics']['mock_tracker']['settings']['api_key']);
    $this->assertEquals('custom.track.example.com', $saved_settings['analytics']['mock_tracker']['settings']['tracking_domain']);
  }

  /**
   * Tests form validation with TimeoutAbDecider.
   */
  public function testTimeoutDeciderValidation(): void {
    // Access the content type edit form.
    $this->drupalGet('admin/structure/types/manage/' . $this->contentType->id());

    // Enable A/B testing.
    $page = $this->getSession()->getPage();
    $page->clickLink('A/B Tests');
    $page->checkField('ab_tests[is_active]');

    // Fill in the default display.
    $page->fillField('ab_tests[default][display_mode]', 'node.full');

    // Select the timeout plugin.
    $this->getSession()->getPage()->selectFieldOption('ab_tests[variants][id]', 'timeout');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Fill in invalid configuration (min > max).
    $this->getSession()->getPage()->fillField('ab_tests[variants][config_wrapper][settings][timeout][min]', '2000');
    $this->getSession()->getPage()->fillField('ab_tests[variants][config_wrapper][settings][timeout][max]', '500');
    // Select only one variant (invalid - needs at least 2).
    $this->getSession()->getPage()->checkField('ab_tests[variants][config_wrapper][settings][available_variants][rss]');

    // Save the form.
    $this->getSession()->getPage()->pressButton('Save');

    // We expect validation errors.
    $this->assertSession()->pageTextContains('The minimum cannot be greater than the maximum.');
    $this->assertSession()->pageTextContains('Select, at least, two variants for the A/B test.');
  }

}
