<?php

declare(strict_types=1);

namespace Drupal\Tests\ab_tests;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\NodeType;

/**
 * Trait for testing.
 */
trait AbTestsTestBaseTrait {

  /**
   * The admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $adminUser;

  /**
   * The test content type.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected NodeType $contentType;

  /**
   * Default permissions for the test user.
   *
   * @var string[]
   */
  protected array $permissions = [
    'administer content types',
    'administer node fields',
    'administer node display',
    'administer node form display',
    'administer nodes',
    'bypass node access',
    'administer site configuration',
    'administer modules',
  ];

  /**
   * Enables the A/B testing for a content type and saves it.
   *
   * @param string $type_id
   *   The content type machine name.
   * @param array $settings
   *   (optional) The AB test settings. Defaults to basic settings with testing
   *   enabled and debug mode on.
   */
  protected function enableAbTestingForContentType(array $settings = []): void {
    // Set default settings if none provided.
    if (empty($settings)) {
      $settings = [
        'is_active' => TRUE,
        'debug' => TRUE,
        'default' => ['display_mode' => 'node.full'],
        'variants' => [
          'id' => 'timeout',
          'settings' => [
            'timeout' => ['min' => 200, 'max' => 250],
            'available_variants' => ['full' => 'full', 'teaser' => 'teaser'],
          ],
        ],
        'analytics' => [
          'id' => 'mock_tracker',
          'settings' => [
            'api_key' => '123asdf',
            'tracking_domain' => 'track.mocktracker.local',
          ],
        ],
      ];
    }

    $this->contentType->setThirdPartySetting('ab_tests', 'ab_tests', $settings);
    $this->contentType->save();
  }

  /**
   * Asserts that certain JavaScript libraries are attached to the page.
   *
   * @param array $libraries
   *   Array of library names to check for.
   */
  protected function assertAttachedLibraries(array $libraries): void {
    $page_libraries = $this->getDrupalSettings()['ajaxPageState']['libraries'] ?? '';
    $libraries_array = explode(',', $page_libraries);

    foreach ($libraries as $library) {
      $this->assertContains(
        $library,
        $libraries_array,
        sprintf('The library %s is not attached to the page.', $library)
      );
    }
  }

}
