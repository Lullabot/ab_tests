<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\FunctionalJavascript;

use Drupal\node\Entity\Node;

/**
 * Tests the node display with A/B testing enabled.
 *
 * @group ab_tests
 */
class NodeDisplayTest extends AbTestsFunctionalJavaScriptTestBase {

  /**
   * Tests the node rendering with A/B testing enabled.
   */
  public function testNodeRendering(): void {
    $this->enableAbTestingForContentType();

    // Create a test node.
    $node = $this->createRandomNode();

    // Visit the node page.
    $this->drupalGet('node/' . $node->id());

    // Verify that the node has the appropriate data attributes.
    $this->assertSession()->elementExists('css', '[data-ab-tests-entity-root="' . $node->uuid() . '"]');

    // Verify variant decider and analytics JavaScript libraries are attached.
    $this->assertAttachedLibraries([
      'ab_variant_decider_timeout/ab_variant_decider.timeout',
    ]);

    // Verify debug mode information is present.
    $settings = $this->getDrupalSettings();
    $this->assertArrayHasKey('ab_tests', $settings);
    $this->assertTrue($settings['ab_tests']['debug']);
    $decider_settings = $settings['ab_tests']['deciderSettings'] ?? [];
    $this->assertEquals(['min' => 200, 'max' => 250], $decider_settings['timeout']);
    $this->assertEquals(['full', 'teaser'], $decider_settings['availableVariants']);
  }

}
