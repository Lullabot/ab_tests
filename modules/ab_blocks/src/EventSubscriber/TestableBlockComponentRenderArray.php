<?php

namespace Drupal\ab_blocks\EventSubscriber;

use Drupal\ab_tests\AbAnalyticsInterface;
use Drupal\ab_tests\AbAnalyticsPluginManager;
use Drupal\ab_tests\AbVariantDeciderInterface;
use Drupal\ab_tests\AbVariantDeciderPluginManager;
use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\EventSubscriber\BlockComponentRenderArray;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\ab_tests\PageService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the render array for testable block components.
 */
final class TestableBlockComponentRenderArray implements EventSubscriberInterface {

  /**
   * Create a new service object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\ab_tests\PageService $pageService
   *   The page service.
   * @param \Drupal\ab_tests\AbVariantDeciderPluginManager $variantDeciderManager
   *   The plugin manager.
   * @param \Drupal\ab_tests\AbAnalyticsPluginManager $analyticsManager
   *   The analytics manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected PageService $pageService,
    protected AbVariantDeciderPluginManager $variantDeciderManager,
    protected AbAnalyticsPluginManager $analyticsManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This should run after rendering the initial block.
    $priority = (BlockComponentRenderArray::getSubscribedEvents()[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY][1] ?? 100) - 20;
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => [
        'onBuildRender',
        $priority,
      ],
    ];
  }

  /**
   * Builds render arrays for block plugins and sets it on the event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $block = $event->getPlugin();
    if (!$block instanceof BlockPluginInterface) {
      return;
    }
    $section_component = $event->getComponent();
    $settings = $section_component->get('additional')['ab_tests'] ?? [];
    $settings['debug'] = $this->configFactory
      ->get('ab_tests.settings')
      ->get('debug_mode');
    // If we are in the Layout Builder interface, or there is no A/B test, then
    // abort.
    if (
      $event->inPreview()
      || empty($settings['is_active'])
      || empty($settings['variants'])
    ) {
      return;
    }
    // Do not affect the Ajax re-render of the block.
    if ($this->routeMatch->getRouteName() === 'ab_blocks.block.ajax_render') {
      // Attach the library from the analytics trackers.
      $build = $event->getBuild();

      // Attach libraries from all analytics trackers.
      $metadata = array_reduce(
        $settings['analytics'] ?? [],
        function (BubbleableMetadata $metadata, array $tracker_config) {
          $tracker_id = $tracker_config['id'] ?? NULL;
          if (!$tracker_id) {
            return $metadata;
          }
          $tracker_settings = $tracker_config['settings'] ?? [];
          try {
            $analytics_tracker = $this->analyticsManager->createInstance(
              $tracker_id,
              $tracker_settings,
            );
            assert($analytics_tracker instanceof AbAnalyticsInterface);
            $metadata->addCacheableDependency(BubbleableMetadata::createFromRenderArray($analytics_tracker->toRenderable()));
          }
          catch (PluginException $e) {
          }
          return $metadata;
        },
        new BubbleableMetadata(),
      );
      $build['content']['#attached'] = NestedArray::mergeDeep($build['content']['#attached'] ?? [], $metadata->getAttachments());
      $build['content']['#attached']['drupalSettings']['ab_tests']['debug'] = (bool) ($settings['debug'] ?? FALSE);

      $event->setBuild($build);
      return;
    }

    // 1. Ensure there is a predictable wrapper that we can use for hiding the
    // block while checking with LaunchDarkly, and for AJAX replacements.
    // Generate a placement ID. This is so we can A/B test multiple placements
    // of the same block in a page.
    $placement_id = $section_component->getUuid();
    $original_build = $event->getBuild();
    $build = [
      '#weight' => $original_build['#weight'] ?? $event->getComponent()
        ->getWeight() ?? 99,
      [
        'content' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['block__ab-testable-block'],
            'data-ab-blocks-placement-id' => $placement_id,
          ],
          // Render the original block, in a hidden state, in case there are
          // client side issues.
          'original' => $original_build,
        ],
      ],
    ];

    // 2. Hide the original block. Then add a class to it to show it if there
    // are JS errors.
    $style = $original_build['#attributes']['style'] ?? [];
    $style[] = 'display: none';
    $build[0]['content']['original']['#attributes']['style'] = $style;
    $class = $original_build['#attributes']['class'] ?? [];
    $class[] = 'block--original';
    $class[] = 'block__ab-testable-block';
    $build[0]['content']['original']['#attributes']['class'] = $class;

    // 4. Save the block context values for later, in the AJAX renderer.
    $serialized_context_values = $this->serializeSupportedContextValues($block);
    try {
      $encoded_context_values = base64_encode(json_encode(
        $serialized_context_values,
        JSON_THROW_ON_ERROR
      ));
    }
    catch (\JsonException $e) {
      $encoded_context_values = base64_encode('[]');
    }

    // 4. Use the decider plugin to get the information from the block.
    $configuration = $block->getConfiguration();
    // Remove the non-custom configuration options.
    $internal_config = array_intersect_key(
      $configuration,
      array_flip([
        'context_mapping',
        'id',
        'label',
        'label_display',
        'provider',
      ])
    );
    // Attach the library from the variant resolver.
    $variant_decider_id = $settings['variants']['id'] ?? 'null';
    try {
      $variant_decider = $this->variantDeciderManager->createInstance(
        $variant_decider_id,
        $settings['variants']['settings'] ?? [],
      );
      assert($variant_decider instanceof AbVariantDeciderInterface);
      $decider_build = $variant_decider->toRenderable(['experimentsSelector' => '[data-ab-blocks-placement-id]']);
    }
    catch (PluginException $e) {
      $decider_build = [
        '#attached' => ['library' => ['ab_tests/ab_variant_decider.null']],
      ];
    }

    // IMPORTANT NOTE: This module currently only works for nodes.
    $root_node = $this->pageService->getNodeFromCurrentRoute();
    // Deal with a core bug that won't bubble up attachments correctly.
    $build[0]['content']['#attached'] = NestedArray::mergeDeep($build[0]['content']['#attached'] ?? [], $decider_build['#attached'] ?? []);
    $build[0]['content']['ab_tests_decider'] = $decider_build;
    $classes = $build[0]['content']['#attributes']['class'] ?? [];
    $build[0]['content']['#attributes']['class'] = [
      ...$classes,
      'ab-test-loading',
    ];
    unset($build[0]['content']['ab_tests_decider']['#attached']);
    // Calculate the Drupal settings using the selected decider.
    $drupal_settings = [];
    $drupal_settings['blocks'][$placement_id] = [
      'variantSettings' => $settings['variants'],
      'pluginId' => $block->getPluginId(),
      'blockSettings' => $internal_config,
      'encodedContext' => $encoded_context_values,
      'contextMetadata' => [
        'rootPage' => ['contentType' => $root_node ? $root_node->bundle() : NULL],
        'block' => ['label' => $block->label()],
      ],
      'placementId' => $placement_id,
      'debug' => (bool) ($settings['debug'] ?? FALSE),
    ];
    $build[0]['content']['#attached']['drupalSettings']['ab_tests']['features']['ab_blocks'] = $drupal_settings;
    $build[0]['content']['#attributes']['data-ab-tests-decider-status'] = 'idle';
    $build[0]['content']['#attributes']['data-ab-blocks-rendered-via'] = 'server';
    $build[0]['content']['#attributes']['data-ab-tests-feature'] = 'ab_blocks';
    $build[0]['content']['#attached']['library'][] = 'ab_blocks/ab_blocks';

    // 6. Save the modified render array.
    $event->setBuild($build);
  }

  /**
   * Serializes the supported context values.
   *
   * We need to do this because blocks get input from where they are placed.
   * But when rendering them in isolation via AJAX we don't have that
   * context. An example of this is the node for the current page.
   *
   * The controller that renders the block in isolation will reconstruct the
   * context for rendering from the output of this method.
   *
   * Example return value: ['entity:node=11234']
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   The block plugin.
   *
   * @return array
   *   The serialized context values.
   */
  protected function serializeSupportedContextValues(BlockPluginInterface $block): array {
    $serialized_context_values = [];
    assert($block instanceof ContextAwarePluginInterface);
    try {
      $contexts = $block->getContexts();
    }
    catch (ContextException $e) {
      return [];
    }
    foreach ($contexts as $key => $context) {
      $data_type = $context->getContextDefinition()->getDataType();
      $typed_data = $context->getContextData();
      // For now, we only support primitive values, and entity contexts.
      if ($typed_data instanceof EntityAdapter) {
        $entity = $context->getContextValue();
        if (!$entity instanceof EntityInterface) {
          continue;
        }
        $serialized_value = $entity->id();
      }
      elseif ($typed_data instanceof PrimitiveInterface) {
        // Use JSON encoding to preserve the casted value after serialization.
        $serialized_value = json_encode($typed_data->getCastedValue());
      }
      else {
        continue;
      }
      $serialized_context_values[$key] = sprintf(
        '%s=%s',
        $data_type,
        $serialized_value,
      );
    }
    return $serialized_context_values;
  }

}
