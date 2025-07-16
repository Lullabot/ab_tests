<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\FunctionalJavascript;

use Drupal\Component\Serialization\Json;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the A/B Tests controller functionality.
 *
 * @group ab_tests
 */
#[Group('ab_tests')]
class ControllerTest extends AbTestsFunctionalJavaScriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->enableAbTestingForContentType([
      'is_active' => TRUE,
      'default' => ['display_mode' => 'node.full'],
      'variants' => [
        'id' => 'timeout_view_mode',
        'settings' => [
          'timeout' => ['min' => 200, 'max' => 250],
          'available_variants' => ['full' => 'full', 'teaser' => 'teaser'],
        ],
      ],
      'analytics' => ['id' => 'null'],
    ]);
  }

  /**
   * Tests the AJAX response from the controller.
   */
  public function testAjaxResponse(): void {
    $node = $this->createRandomNode();

    // Access the AJAX controller URL directly.
    $url = '/ab-tests/render/' . $node->uuid() . '/teaser';
    $this->drupalGet($url, [
      'query' => [
        '_wrapper_format' => 'drupal_ajax',
      ],
    ]);

    // The command puts the JSON inside a textarea element. We need to extract
    // it using XPath.
    $nodes = $this->xpath('//textarea');
    $json_node = reset($nodes);
    $response = Json::decode($json_node->getText());

    // Check that the response is an array of commands.
    $this->assertIsArray($response);
    $this->assertNotEmpty($response);

    // Check that a ReplaceCommand is included.
    $replace_command_found = FALSE;
    $settings_command_found = FALSE;
    foreach ($response as $command) {
      if ($command['command'] === 'insert' && $command['method'] === 'replaceWith') {
        $replace_command_found = TRUE;
        // Verify the HTML contains the expected data attribute.
        $this->assertStringContainsString('data-ab-tests-decision="teaser"', $command['data']);
        // Verify the selector targets the expected node.
        $this->assertStringContainsString('data-ab-tests-entity-root="' . $node->uuid() . '"', $command['selector']);
      }
      if ($command['command'] === 'settings') {
        $settings_command_found = TRUE;
        $this->assertSame(
          ['analyticsSettings' => ['id' => 'null'], 'debug' => FALSE],
          $command['settings']['ab_tests'],
        );
      }
      if ($command['command'] === 'add_js') {
        $analytics_js = array_filter(
          array_map(static fn (array $info) => $info['src'], $command['data']),
          static fn (string $url) => preg_match('~^/modules/contrib/ab_tests/js/ab-analytics-tracker-null.js\??~', $url),
        );
        $this->assertNotEmpty($analytics_js);
        $analytics_js = array_filter(
          array_map(static fn (array $info) => $info['src'], $command['data']),
          static fn (string $url) => preg_match('~^/modules/contrib/ab_tests/js/ab-analytics-tracker-null.js\??~', $url),
        );
        $this->assertNotEmpty($analytics_js);
      }
    }
    $this->assertTrue($replace_command_found, 'Replace command was found in the AJAX response');
    $this->assertTrue($settings_command_found, 'Settings command was found in the AJAX response');

    // Test with a non-existent UUID.
    $this->drupalGet('/ab-tests/render/non-existent-uuid/default', [
      'query' => ['_wrapper_format' => 'drupal_ajax'],
    ]);

    // Verify we get a 404 response.
    $this->assertEmpty($this->xpath('//textarea'));
  }

}
