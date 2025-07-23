<?php

declare(strict_types=1);

namespace Drupal\ab_blocks\Plugin\Block;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Psr\Log\LoggerInterface;
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
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * Cached target block instance.
   */
  protected ?BlockPluginInterface $targetBlockInstance = NULL;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    BlockManagerInterface $block_manager,
    LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockManager = $block_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.block'),
      $container->get('logger.factory')->get('ab_blocks')
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
      'context_mapping' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    // Get all block plugin definitions and filter to block plugins only.
    $block_definitions = $this->blockManager->getDefinitions();
    $block_options = [];

    foreach ($block_definitions as $plugin_id => $definition) {
      // Skip the proxy block itself to avoid recursion.
      if ($plugin_id === $this->getPluginId()) {
        continue;
      }
      
      $block_options[$plugin_id] = $definition['admin_label'] ?? $plugin_id;
    }

    // Sort options alphabetically.
    asort($block_options);

    $form['target_block_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Target Block'),
      '#description' => $this->t('Select the block plugin to proxy.'),
      '#options' => ['' => $this->t('- Select a block -')] + $block_options,
      '#default_value' => $config['target_block_plugin'] ?? '',
      '#required' => TRUE,
    ];

    $form['render_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Render Mode'),
      '#description' => $this->t('How to render the block.'),
      '#options' => [
        'block' => $this->t('Block'),
        'empty' => $this->t('Empty (hidden)'),
      ],
      '#default_value' => $config['render_mode'] ?? 'block',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $this->configuration['target_block_plugin'] = $form_state->getValue('target_block_plugin');
    $this->configuration['render_mode'] = $form_state->getValue('render_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->getConfiguration();

    // Handle empty render mode.
    if ($config['render_mode'] === 'empty') {
      return [];
    }

    // Create and render target block.
    $target_block = $this->getTargetBlock();
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
   * Gets or creates the target block plugin instance.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   The target block plugin or NULL if creation failed.
   */
  protected function getTargetBlock(): ?BlockPluginInterface {
    if ($this->targetBlockInstance === NULL) {
      $this->targetBlockInstance = $this->createTargetBlock();
    }
    return $this->targetBlockInstance;
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
      $target_block = $this->blockManager->createInstance($plugin_id, $block_config);
      
      // Pass contexts to the target block if it's context-aware.
      if ($target_block instanceof ContextAwarePluginInterface) {
        $this->passContextsToTargetBlock($target_block);
      }
      
      return $target_block;
    }
    catch (PluginException $e) {
      $this->logger->warning('Failed to create target block @plugin: @message', [
        '@plugin' => $plugin_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Passes contexts from the proxy block to the target block.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $target_block
   *   The target block plugin.
   */
  protected function passContextsToTargetBlock(ContextAwarePluginInterface $target_block): void {
    if (!$this instanceof ContextAwarePluginInterface) {
      return;
    }

    try {
      $proxy_contexts = $this->getContexts();
      $context_mapping = $this->getConfiguration()['context_mapping'] ?? [];
      
      // Get the target block's context definitions.
      $target_context_definitions = $target_block->getContextDefinitions();
      
      foreach ($target_context_definitions as $target_context_name => $definition) {
        // Check if there's a mapping for this context.
        $source_context_name = $context_mapping[$target_context_name] ?? $target_context_name;
        
        // If we have a matching context, pass it to the target block.
        if (isset($proxy_contexts[$source_context_name])) {
          $target_block->setContext($target_context_name, $proxy_contexts[$source_context_name]);
        }
      }
    }
    catch (ContextException $e) {
      $this->logger->warning('Failed to pass contexts to target block: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Bubbles cache metadata from the target block to the render array.
   *
   * @param array $build
   *   The render array to apply metadata to.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface&\Drupal\Core\Access\AccessibleInterface $target_block
   *   The target block plugin.
   */
  protected function bubbleTargetBlockCacheMetadata(array &$build, CacheableDependencyInterface&AccessibleInterface $target_block): void {
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
   * Helper method to get target block cache metadata.
   *
   * @param string $type
   *   The cache metadata type ('contexts', 'tags', or 'max-age').
   * @param mixed $parent_value
   *   The parent cache metadata value.
   *
   * @return mixed
   *   The merged cache metadata.
   */
  protected function getTargetBlockCacheMetadata(string $type, $parent_value) {
    $config = $this->getConfiguration();
    if (empty($config['target_block_plugin'])) {
      return $parent_value;
    }

    $target_block = $this->getTargetBlock();
    if (!$target_block) {
      return $parent_value;
    }

    switch ($type) {
      case 'contexts':
        return Cache::mergeContexts($parent_value, $target_block->getCacheContexts());

      case 'tags':
        return Cache::mergeTags($parent_value, $target_block->getCacheTags());

      case 'max-age':
        return Cache::mergeMaxAges($parent_value, $target_block->getCacheMaxAge());

      default:
        return $parent_value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->getTargetBlockCacheMetadata('contexts', parent::getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = $this->getTargetBlockCacheMetadata('tags', parent::getCacheTags());
    
    // Add config-based cache tag.
    $cache_tags[] = 'ab_test_proxy_block:' . $this->getPluginId();

    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->getTargetBlockCacheMetadata('max-age', parent::getCacheMaxAge());
  }

}
