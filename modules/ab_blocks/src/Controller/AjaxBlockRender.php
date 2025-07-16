<?php

declare(strict_types=1);

namespace Drupal\ab_blocks\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a drupal block in isolation using client-side generated block config.
 */
final class AjaxBlockRender extends ControllerBase {

  /**
   * Creates an AjaxBlockRender object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block manager.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed data manager.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected BlockManagerInterface $blockManager,
    protected TypedDataManagerInterface $typedDataManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('renderer'),
      $container->get('plugin.manager.block'),
      $container->get('typed_data_manager'),
    );
  }

  /**
   * Renders the entity as an Ajax response.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param string $placement_id
   *   The placement ID.
   * @param string $encoded_config
   *   The base64 JSON encoded configuration for the block.
   * @param string $encoded_contexts
   *   The base64 JSON encoded serialized contexts.
   *
   * @return \Drupal\Core\Cache\CacheableAjaxResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __invoke(string $plugin_id, string $placement_id, string $encoded_config, string $encoded_contexts): CacheableAjaxResponse {
    $response = new CacheableAjaxResponse();
    $json_config = base64_decode($encoded_config);
    $json_contexts = base64_decode($encoded_contexts);
    try {
      $configuration = json_decode($json_config, TRUE, 512, JSON_THROW_ON_ERROR);
      $serialized_context_values = json_decode($json_contexts, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      return $response;
    }
    $context_values = $this->deserializeContextValues($serialized_context_values);
    $block = $this->createBlockPluginInstance($plugin_id, $configuration, $context_values);
    assert($block instanceof ContextAwarePluginInterface);
    $build = $this->renderAsBlock($block, $placement_id);
    $context = new RenderContext();
    $html = $this->renderer->executeInRenderContext($context, function () use ($build) {
      return $this->renderer->render($build);
    });

    $metadata_from_render = $context->pop();
    assert($metadata_from_render instanceof BubbleableMetadata);
    $attachments_from_render = $metadata_from_render->getAttachments();
    // Add caching information for the render metadata.
    $response->addCacheableDependency($metadata_from_render);
    // Add the attachments from the render process.
    $response->addAttachments($attachments_from_render);

    $dependency = new BubbleableMetadata();
    $dependency->addCacheContexts([
      'url.query_args:_wrapper_format',
      'url.query_args:js',
      'url.query_args:_drupal_ajax',
      'url.query_args:ajax_page_state',
    ]);
    $response->addCacheableDependency($dependency);

    // The selector for the insert command is NULL as the new content will
    // replace the element making the Ajax call.
    $response->addCommand(new InsertCommand(NULL, $html));
    return $response;
  }

  /**
   * Simulates the block view builder for a block plugin.
   *
   * This is necessary to have HTML parity with the non-AJAX version of the
   * block.
   *
   * @param \Drupal\Core\Plugin\ContextAwarePluginInterface $block
   *   The block plugin.
   * @param string $placement_id
   *   The placement ID.
   *
   * @return array
   *   The render array.
   *
   * @see \Drupal\layout_builder\EventSubscriber\BlockComponentRenderArray
   */
  protected function renderAsBlock(ContextAwarePluginInterface $block, string $placement_id): array {
    $access = $block->access($this->currentUser(), TRUE);
    if (!$access->isAllowed()) {
      return [];
    }
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheableDependency($block);
    $content = $block->build();
    $cache_metadata->addCacheableDependency(CacheableMetadata::createFromRenderArray($content));
    $build = [
      // This may be moved to BlockBase in https://www.drupal.org/node/2931040.
      '#theme' => 'block',
      '#configuration' => $block->getConfiguration(),
      '#plugin_id' => $block->getPluginId(),
      '#base_plugin_id' => $block->getBaseId(),
      '#derivative_plugin_id' => $block->getDerivativeId(),
      '#in_preview' => FALSE,
    ];

    if (isset($content['#attributes'])) {
      $build['#attributes'] = $content['#attributes'];
      unset($content['#attributes']);
    }
    $build['#attributes']['data-ab-blocks-placement-id'] = $placement_id;
    $build['#attributes']['data-ab-blocks-rendered-via'] = 'ajax';
    $build['#attributes']['class'] = ['block__ab-testable-block'];
    $build['content'] = $content;
    return $build;
  }

  /**
   * Instantiate a block plugin based on ID, config, and context.
   *
   * @param string $plugin_id
   *   The block plugin ID.
   * @param array $configuration
   *   The block configuration, including core's default keys.
   * @param array $context_values
   *   The associative array containing context values.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createBlockPluginInstance(string $plugin_id, array $configuration, array $context_values): BlockPluginInterface {
    $block = $this->blockManager->createInstance($plugin_id, $configuration);
    assert($block instanceof ContextAwarePluginInterface);
    foreach ($context_values as $key => $context_value) {
      $block->setContextValue($key, $context_value);
    }
    assert($block instanceof BlockPluginInterface);
    return $block;
  }

  /**
   * Deserializes context values.
   *
   * @param array $serialized_context_values
   *   The serialized context values.
   *
   * @return array
   *   The deserialized context values.
   */
  protected function deserializeContextValues(array $serialized_context_values): array {
    $deserialized = array_map(
      [$this, 'deserializeContextValue'],
      $serialized_context_values
    );
    return array_filter($deserialized, static fn($val) => !is_null($val));
  }

  /**
   * Deserializes a single context value.
   *
   * @param string $serialized_context_value
   *   The serialized context value.
   *
   * @return mixed
   *   The context value.
   */
  protected function deserializeContextValue(string $serialized_context_value) {
    [$data_type, $data_value] = explode('=', $serialized_context_value);
    try {
      $typed_data_definition = $this->typedDataManager->getDefinition($data_type);
      $typed_data_class = $typed_data_definition['class'] ?? NULL;
    }
    catch (PluginException $exception) {
      return NULL;
    }
    // Check if the serialized context value is supported.
    if (is_a($typed_data_class, EntityAdapter::class, TRUE)) {
      // For now, we only support entity contexts.
      [, $entity_type_id] = explode(':', $data_type);
      try {
        return $this->entityTypeManager()
          ->getStorage($entity_type_id)
          ->load($data_value);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException  $e) {
      }
    }
    if (is_a($typed_data_class, PrimitiveInterface::class, TRUE)) {
      return json_decode($data_value);
    }
    return NULL;
  }

}
