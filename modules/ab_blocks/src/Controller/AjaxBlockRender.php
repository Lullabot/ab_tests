<?php

declare(strict_types=1);

namespace Drupal\ab_blocks\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected BlockManagerInterface $blockManager,
    protected TypedDataManagerInterface $typedDataManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('renderer'),
      $container->get('plugin.manager.block'),
      $container->get('typed_data_manager'),
      $container->get('entity_display.repository'),
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
  public function __invoke(
    string $plugin_id,
    string $placement_id,
    string $encoded_config,
    string $encoded_contexts,
  ): CacheableAjaxResponse {
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
    $entities = array_filter(
      $context_values,
      static fn($context) => $context instanceof ContentEntityInterface,
    );

    $section_component = array_reduce(
      $entities,
      fn (?SectionComponent $component, ContentEntityInterface $entity): ?SectionComponent =>
        $component ?: $this->findLayoutBuilderComponent($entity, $placement_id),
    );

    // If no section component found, return empty response.
    if (!$section_component) {
      return $response;
    }

    try {
      $settings = $section_component->get('additional')['ab_tests'] ?? [];
      $settings['debug'] = $this->config('ab_tests.settings')
        ->get('debug_mode');
      $section_component->setConfiguration($configuration);
      $build = $section_component->toRenderArray($context_values);
      // This is used in the analytics plugins (js) to detect the block to
      // track.
      $build['#attributes']['data-ab-tests-tracking-info'] = $placement_id;
    }
    catch (\Exception $e) {
      // If configuration or build creation fails, return an empty  response.
      return $response;
    }

    $context = new RenderContext();
    try {
      $html = $this->renderer->executeInRenderContext($context, function() use ($build) {
        return $this->renderer->render($build);
      });
    }
    catch (\Exception $e) {
      // If rendering fails, return empty response.
      return $response;
    }

    $metadata_from_render = $context->pop();
    if ($metadata_from_render instanceof BubbleableMetadata) {
      $attachments_from_render = $metadata_from_render->getAttachments();
      // Add caching information for the render metadata.
      $response->addCacheableDependency($metadata_from_render);
      // Add the attachments from the render process.
      $response->addAttachments($attachments_from_render);
    }

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
      catch (InvalidPluginDefinitionException|PluginNotFoundException  $e) {
      }
    }
    if (is_a($typed_data_class, PrimitiveInterface::class, TRUE)) {
      try {
        return json_decode($data_value, TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException $e) {
        return NULL;
      }
    }
    return NULL;
  }

  /**
   * Retrieves a Layout Builder component by its UUID.
   *
   * This function first checks if the given entity has a layout override. If
   * so, it searches for the component within that override. If the entity uses
   * the default layout or the component is not found in the override, it then
   * searches the default layout for the entity's bundle and view mode.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity (e.g., a Node) from which to get the layout.
   * @param string $component_uuid
   *   The UUID (placement ID) of the component to retrieve.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   The SectionComponent object if found, otherwise NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function findLayoutBuilderComponent(ContentEntityInterface $entity, string $component_uuid): ?SectionComponent {
    // First check for a per-entity override.
    $component = $this->findComponentInEntityOverride($entity, $component_uuid);

    // If not found, fall back to checking the default layout of ALL view modes.
    return $component ?? $this->findComponentInDefaultLayouts($entity, $component_uuid);
  }

  /**
   * Finds a component in the entity's layout override field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to search.
   * @param string $component_uuid
   *   The UUID of the component to find.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   The component if found, otherwise NULL.
   */
  private function findComponentInEntityOverride(ContentEntityInterface $entity, string $component_uuid): ?SectionComponent {
    if (!$entity->hasField('layout_builder__layout')) {
      return NULL;
    }

    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $layout_field */
    $layout_field = $entity->get('layout_builder__layout');
    if ($layout_field->isEmpty()) {
      return NULL;
    }

    // Use array_reduce to find the first component that matches the UUID.
    $sections = iterator_to_array($layout_field);
    return array_reduce($sections, function(?SectionComponent $carry, $section_list_item) use ($component_uuid) {
      if ($carry !== NULL) {
        return $carry;
      }
      /** @var \Drupal\layout_builder\Section $section */
      $section = $section_list_item->section;
      return $section->getComponent($component_uuid);
    });
  }

  /**
   * Finds a component in the default layouts across all view modes.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to search.
   * @param string $component_uuid
   *   The UUID of the component to find.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   The component if found, otherwise NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function findComponentInDefaultLayouts(ContentEntityInterface $entity, string $component_uuid): ?SectionComponent {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // Get all available view modes for this entity's bundle.
    $view_modes = $this->entityDisplayRepository
      ->getViewModeOptionsByBundle($entity_type_id, $bundle);

    // Find the first component across all view modes.
    return array_reduce(array_keys($view_modes), function(?SectionComponent $carry, string $view_mode_name) use ($entity_type_id, $bundle, $component_uuid) {
      if ($carry !== NULL) {
        return $carry;
      }

      $display_id = "{$entity_type_id}.{$bundle}.{$view_mode_name}";
      try {
        $display = $this->entityTypeManager()
          ->getStorage('entity_view_display')
          ->load($display_id);
      }
      catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
        return NULL;
      }

      if (!$display instanceof LayoutBuilderEntityViewDisplay) {
        return NULL;
      }

      // Find the first component in this view mode's sections.
      return array_reduce(
        $display->getSections(),
        function(?SectionComponent $section_carry, Section $section) use ($component_uuid) {
          if ($section_carry !== NULL) {
            return $section_carry;
          }
          $section_components = $section->getComponents();
          return $section_components[$component_uuid] ?? NULL;
        },
      );
    });
  }

}
