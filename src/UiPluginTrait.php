<?php

namespace Drupal\ab_tests;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\Markup;

trait UiPluginTrait {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function description(): MarkupInterface {
    return $this->pluginDefinition['description'] ?? Markup::create('');
  }

}
