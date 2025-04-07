<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\Component\Render\MarkupInterface;

/**
 * An interface for plugin manages that go into the UI.
 */
interface UiPluginManagerInterface {

  /**
   * Instantiates all the plugins.
   *
   * @param array|null $plugin_ids
   *   The IDs to load.
   * @param array $settings
   *   The settings for the providers keyed by the plugin ID.
   *
   * @return \Drupal\ab_tests\AbVariantDeciderInterface[]
   *   The plugin instances.
   */
  public function getPlugins(?array $plugin_ids = NULL, array $settings = []): array;

  /**
   * Get the label for the plugin type.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The label.
   */
  public function getPluginTypeLabel(): MarkupInterface;

  /**
   * Get the section name for the plugin type.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The name.
   */
  public function getPluginTypeSectionName(): MarkupInterface;

  /**
   * Get the description for the plugin type.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The description.
   */
  public function getPluginTypeDescription(): MarkupInterface;

}
