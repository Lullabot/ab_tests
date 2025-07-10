<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\Functional;

use Drupal\Tests\ab_tests\AbTestsTestBaseTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the A/B Tests module lifecycle.
 *
 * @group ab_tests
 */
class ModuleLifecycleTest extends BrowserTestBase {

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
    // Don't include ab_tests yet as we'll enable it in the test.
  ];

  /**
   * A/B settings.
   *
   * @var array
   */
  protected array $contentTypeAbSettings;

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
    $this->contentTypeAbSettings = [
      'is_active' => TRUE,
      'default' => ['display_mode' => 'node.full'],
      'variants' => ['id' => 'null'],
      'analytics' => ['id' => 'null'],
    ];
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests enabling and disabling the main module.
   */
  public function testEnableDisableMainModule(): void {
    // Verify that the module is not yet enabled.
    $module_handler = $this->container->get('module_handler');
    $this->assertFalse($module_handler->moduleExists('ab_tests'), 'A/B Tests module is not yet enabled.');

    // Enable the AB Tests module.
    \Drupal::service('module_installer')->install([
      'ab_tests',
    ]);
    $this->rebuildContainer();
    $this->enableAbTestingForContentType($this->contentTypeAbSettings);

    // Verify that the module is now enabled.
    $module_handler = \Drupal::service('module_handler');
    $this->assertTrue($module_handler->moduleExists('ab_tests'), 'A/B Tests module is now enabled.');

    // Check that the module's routes are available.
    $this->drupalGet('admin/config/search/ab-tests');
    $this->assertSession()->statusCodeEquals(200);

    // Uninstall the module.
    \Drupal::service('module_installer')->uninstall(['ab_tests']);
    $this->rebuildContainer();

    // Verify that the module is now disabled.
    $module_handler = \Drupal::service('module_handler');
    $this->assertFalse($module_handler->moduleExists('ab_tests'), 'A/B Tests module is now disabled.');

    // Check that the module's routes are no longer available.
    $this->drupalGet('admin/config/search/ab-tests');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests enabling and disabling example modules.
   */
  public function testEnableDisableExampleModules(): void {
    // Enable the AB Tests module first.
    \Drupal::service('module_installer')->install(['ab_tests']);
    $this->rebuildContainer();
    $this->enableAbTestingForContentType($this->contentTypeAbSettings);

    // Verify that the example modules are not yet enabled.
    $module_handler = \Drupal::service('module_handler');
    $this->assertFalse($module_handler->moduleExists('ab_analytics_tracker_example'), 'AB Analytics Tracker Example module is not yet enabled.');
    $this->assertFalse($module_handler->moduleExists('ab_variant_decider_view_mode_timeout'), 'AB Variant Decider Timeout module is not yet enabled.');

    // Enable the example modules.
    \Drupal::service('module_installer')->install(['ab_analytics_tracker_example', 'ab_variant_decider_view_mode_timeout']);
    $this->rebuildContainer();

    // Verify that the example modules are now enabled.
    $module_handler = \Drupal::service('module_handler');
    $this->assertTrue($module_handler->moduleExists('ab_analytics_tracker_example'), 'AB Analytics Tracker Example module is now enabled.');
    $this->assertTrue($module_handler->moduleExists('ab_variant_decider_view_mode_timeout'), 'AB Variant Decider Timeout module is now enabled.');

    // Uninstall the example modules.
    \Drupal::service('module_installer')->uninstall(['ab_analytics_tracker_example', 'ab_variant_decider_view_mode_timeout']);
    $this->rebuildContainer();

    // Verify that the example modules are now disabled.
    $module_handler = \Drupal::service('module_handler');
    $this->assertFalse($module_handler->moduleExists('ab_analytics_tracker_example'), 'AB Analytics Tracker Example module is now disabled.');
    $this->assertFalse($module_handler->moduleExists('ab_variant_decider_view_mode_timeout'), 'AB Variant Decider Timeout module is now disabled.');
  }

  /**
   * Tests uninstalling with existing content and configurations.
   */
  public function testUninstallWithContent(): void {
    // Enable the AB Tests module and example modules.
    \Drupal::service('module_installer')->install(['ab_tests']);
    $this->rebuildContainer();
    $this->enableAbTestingForContentType($this->contentTypeAbSettings);

    $node = $this->drupalCreateNode(['type' => 'ab_test_type']);
    // Visit the node to verify it works without A/B testing.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Uninstall the AB Tests module.
    \Drupal::service('module_installer')->uninstall(['ab_tests']);
    $this->rebuildContainer();

    // Visit the node again to verify it still works without A/B testing.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Rebuilds the container to ensure new services are available.
   */
  protected function rebuildContainer(): void {
    $this->container = $this->initKernel(\Drupal::request());
  }

}
