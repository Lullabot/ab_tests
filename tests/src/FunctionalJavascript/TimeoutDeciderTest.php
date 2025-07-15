<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the timeout decider functionality.
 *
 * @group ab_tests
 */
#[Group('ab_tests')]
class TimeoutDeciderTest extends AbTestsFunctionalJavaScriptTestBase {

  /**
   * Tests that the timeout decider correctly switches view modes.
   */
  public function testTimeoutDeciderViewModeSwitching(): void {
    $this->drupalLogin($this->adminUser);
    $this->enableAbTestingForContentType();

    // Create a test node.
    $node = $this->createRandomNode();
    $this->drupalGet('node/' . $node->id());

    // Assert initial state.
    $this->assertSession()->elementExists('css', '[data-ab-tests-entity-root]');
    $this->assertSession()->elementExists('css', '[data-ab-tests-decider-status]');

    // @todo Figure out how to assert that the new content is rendered with the correct attributes.
  }

}
