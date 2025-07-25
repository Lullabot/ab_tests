<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the node display with A/B testing enabled.
 *
 * @group ab_tests
 */
#[Group('ab_tests')]
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
    $this->assertSession()->elementExists('css', '[data-ab-tests-instance-id="' . $node->uuid() . '"]');

    // Verify variant decider and analytics JavaScript libraries are attached.
    $this->assertAttachedLibraries(['ab_tests/ab_variant_decider.timeout']);

    $settings = $this->getDrupalSettings();
    $this->assertArrayHasKey('ab_tests', $settings);
    $decider_settings = $settings['ab_tests']['deciderSettings'] ?? [];
    $this->assertEquals(['min' => 200, 'max' => 250], $decider_settings['timeout']);
    $this->assertEquals(['full', 'teaser'], $decider_settings['availableVariants']);
    $this->assertEquals('[data-ab-tests-instance-id]', $decider_settings['experimentsSelector']);
    $this->assertEquals('full', $settings['ab_tests']['features']['ab_view_modes']['defaultDecisionValue']);
    $this->assertFalse($settings['ab_tests']['debug']);
  }

}
