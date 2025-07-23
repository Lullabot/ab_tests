<?php

declare(strict_types=1);

namespace Drupal\ab_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an A/B Test Proxy Block.
 *
 * @Block(
 *   id = "ab_test_proxy_block",
 *   admin_label = @Translation("A/B Test Proxy Block"),
 *   category = @Translation("A/B Testing")
 * )
 */
class AbTestProxyBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The block manager service.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * Constructs a new AbTestProxyBlock.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    BlockManagerInterface $block_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.block')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'target_block_plugin' => '',
      'target_block_config' => [],
      'render_mode' => 'block',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Form implementation will be added in Issue B.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    // Configuration save implementation will be added in Issue B.
    $form['foo'] = ['#markup' => 'Bar'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->getConfiguration();

    // Handle empty render mode.
    if ($config['render_mode'] === 'empty') {
      return [
        '#markup' => '',
        '#cache' => [
          'contexts' => $this->getCacheContexts(),
          'tags' => $this->getCacheTags(),
          'max-age' => $this->getCacheMaxAge(),
        ],
      ];
    }

    // Create and render target block.
    $target_block = $this->createTargetBlock();
    if (!$target_block) {
      return $this->buildErrorState();
    }

    // Check target block access.
    $access_result = $target_block->access(\Drupal::currentUser(), TRUE);
    if (!$access_result->isAllowed()) {
      $build = [
        '#markup' => '',
        '#cache' => [
          'contexts' => $this->getCacheContexts(),
          'tags' => $this->getCacheTags(),
          'max-age' => $this->getCacheMaxAge(),
        ],
      ];
      CacheableMetadata::createFromObject($access_result)->applyTo($build);
      return $build;
    }

    // Build target block.
    $build = $target_block->build();

    // Bubble up cache metadata from target block.
    $this->bubbleTargetBlockCacheMetadata($build, $target_block);

    return $build;
  }

  /**
   * Creates the target block plugin instance.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The target block plugin or NULL if creation failed.
   */
  protected function createTargetBlock(): ?BlockPluginInterface {
    $config = $this->getConfiguration();
    $plugin_id = $config['target_block_plugin'] ?? '';
    $block_config = $config['target_block_config'] ?? [];

    if (empty($plugin_id)) {
      return NULL;
    }

    try {
      return $this->blockManager->createInstance($plugin_id, $block_config);
    }
    catch (PluginException $e) {
      \Drupal::logger('ab_blocks')->warning('Failed to create target block @plugin: @message', [
        '@plugin' => $plugin_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Bubbles cache metadata from the target block to the render array.
   *
   * @param array $build
   *   The render array to apply metadata to.
   * @param \Drupal\Core\Block\BlockPluginInterface $target_block
   *   The target block plugin.
   */
  protected function bubbleTargetBlockCacheMetadata(array &$build, BlockPluginInterface $target_block): void {
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);

    // Add target block's cache contexts, tags, and max-age.
    $cache_metadata->addCacheContexts($target_block->getCacheContexts());
    $cache_metadata->addCacheTags($target_block->getCacheTags());
    $cache_metadata->setCacheMaxAge(
      Cache::mergeMaxAges($cache_metadata->getCacheMaxAge(), $target_block->getCacheMaxAge())
    );

    // Add proxy block's own cache metadata.
    $cache_metadata->addCacheContexts($this->getCacheContexts());
    $cache_metadata->addCacheTags($this->getCacheTags());
    $cache_metadata->setCacheMaxAge(
      Cache::mergeMaxAges($cache_metadata->getCacheMaxAge(), $this->getCacheMaxAge())
    );

    $cache_metadata->applyTo($build);
  }

  /**
   * Builds an error state render array.
   *
   * @return array
   *   The error state render array.
   */
  protected function buildErrorState(): array {
    return [
      '#markup' => $this->t('Block configuration error: Target block could not be loaded.'),
      '#cache' => [
        'contexts' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags(),
        'max-age' => $this->getCacheMaxAge(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $cache_contexts = parent::getCacheContexts();

    // Add contexts based on configuration.
    $config = $this->getConfiguration();
    if (!empty($config['target_block_plugin'])) {
      $target_block = $this->createTargetBlock();
      if ($target_block) {
        $cache_contexts = Cache::mergeContexts($cache_contexts, $target_block->getCacheContexts());
      }
    }

    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = parent::getCacheTags();

    $config = $this->getConfiguration();
    if (!empty($config['target_block_plugin'])) {
      $target_block = $this->createTargetBlock();
      if ($target_block) {
        $cache_tags = Cache::mergeTags($cache_tags, $target_block->getCacheTags());
      }
    }

    // Add config-based cache tag.
    $cache_tags[] = 'ab_test_proxy_block:' . $this->getPluginId();

    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $max_age = parent::getCacheMaxAge();

    $config = $this->getConfiguration();
    if (!empty($config['target_block_plugin'])) {
      $target_block = $this->createTargetBlock();
      if ($target_block) {
        $max_age = Cache::mergeMaxAges($max_age, $target_block->getCacheMaxAge());
      }
    }

    return $max_age;
  }

}
