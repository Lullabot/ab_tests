<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Hook;

use Drupal\ab_tests\AbAnalyticsInterface;
use Drupal\ab_tests\AbAnalyticsPluginManager;
use Drupal\ab_tests\AbVariantDeciderInterface;
use Drupal\ab_tests\AbVariantDeciderPluginManager;
use Drupal\ab_tests\EntityHelper;
use Drupal\ab_tests\Form\PluginSelectionFormTrait;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for ab_tests.
 */
class AbTestsHooks {

  use StringTranslationTrait;
  use PluginSelectionFormTrait;

  /**
   * An array to store cached settings.
   */
  private array $cache = [];

  /**
   * Constructor for the class.
   *
   * @param \Drupal\ab_tests\EntityHelper $entityHelper
   *   The entity helper.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\ab_tests\AbVariantDeciderPluginManager $variantDeciderManager
   *   The variant decider manager.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\ab_tests\AbAnalyticsPluginManager $analyticsManager
   *   The analytics manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly EntityHelper $entityHelper,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    private readonly RequestStack $requestStack,
    private readonly AbVariantDeciderPluginManager $variantDeciderManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AbAnalyticsPluginManager $analyticsManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match) {
    if ($route_name !== 'help.page.ab_tests') {
      return NULL;
    }
    return '<p>' . $this->t('The A/B Tests module helps to set up A/B tests to optimize user experiences.') . '</p>';
  }

  /**
   * Implements hook_entity_view_mode_alter().
   */
  #[Hook('entity_view_mode_alter')]
  public function entityViewModeAlter(&$view_mode, EntityInterface $entity): void {
    // Do not affect the Ajax re-render of the entity.
    if ($this->routeMatch->getRouteName() === 'ab_tests.render_variant') {
      return;
    }
    // @todo Should `full` be configurable?
    if (!$this->isFullPageEntity($entity) || $view_mode !== 'full') {
      return;
    }
    $settings = $this->getSettings($entity);
    $is_active = (bool) ($settings['is_active'] ?? FALSE);
    if (!$is_active) {
      return;
    }
    if (empty($settings['default']['display_mode'])) {
      return;
    }
    $view_mode = substr($settings['default']['display_mode'], strlen($entity->getEntityTypeId()) + 1);
  }

  /**
   * Determines if the given entity is the full page entity for the request.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity is the full page entity for the current request,
   *   FALSE otherwise.
   */
  private function isFullPageEntity(EntityInterface $entity): bool {
    // @todo Use the PageService from PH Tools to achieve this.
    $request = $this->requestStack->getCurrentRequest();
    $page_entity = $request
      ->get($entity->getEntityTypeId());
    if ($page_entity instanceof EntityInterface) {
      return $page_entity->uuid() === $entity->uuid();
    }
    $uuid = $request->get('uuid');
    return $uuid === $entity->uuid();
  }

  /**
   * Retrieves A/B testing settings for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the settings are to be retrieved.
   *
   * @return array
   *   An associative array containing A/B testing settings for the entity.
   *   Returns a default 'is_active' => FALSE setting if A/B tests are not
   *   enabled for the entity or if the entity does not match the current
   *   request.
   */
  private function getSettings(EntityInterface $entity): array {
    $cid = sprintf(
      'ab-tests-settings#%s:%s',
      $entity->getEntityTypeId(),
      $entity->bundle(),
    );
    if (isset($this->cache[$cid])) {
      return $this->cache[$cid];
    }
    // Only act when viewing the page for the current node.
    // Return early if A/B tests are not enabled for this particular bundle.
    $bundle_entity = $this->entityHelper->getBundle($entity);
    $settings = $bundle_entity?->getThirdPartySetting('ab_tests', 'ab_tests', []) ?? [];
    $settings['debug'] = $this->configFactory->get('ab_tests.settings')->get('debug_mode');
    $this->cache[$cid] = $settings;
    return $settings;
  }

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    $settings = $this->getSettings($entity);
    $is_active = (bool) ($settings['is_active'] ?? FALSE);
    if (!$is_active) {
      return;
    }
    // Do not affect the Ajax re-render of the entity.
    if ($this->routeMatch->getRouteName() === 'ab_tests.render_variant') {
      // Attach the library from the analytics tracker.
      $analytics_tracker_id = $settings['analytics']['id'] ?? 'null';
      try {
        $analytics_tracker = $this->analyticsManager->createInstance(
          $analytics_tracker_id,
          $settings['analytics']['settings'] ?? [],
        );
        assert($analytics_tracker instanceof AbAnalyticsInterface);
        $tracker_build = $analytics_tracker->toRenderable();
      }
      catch (PluginException $e) {
        $tracker_build = [
          '#attached' => ['library' => ['ab_tests/ab_analytics_tracker.null']],
        ];
      }
      $build['ab_tests_tracker'] = $tracker_build;
      $build['#attached'] = NestedArray::mergeDeep($build['#attached'] ?? [], $tracker_build['#attached'] ?? []);
      $build['#attached']['drupalSettings']['ab_tests']['debug'] = (bool) ($settings['debug'] ?? FALSE);

      $build['ab_tests_tracker'] = $tracker_build;
      unset($build['ab_tests_tracker']['#attached']);
      return;
    }
    if (!$this->isFullPageEntity($entity)) {
      return;
    }
    // Attach the library from the variant resolver.
    $variant_decider_id = $settings['variants']['id'] ?? 'null';
    try {
      $variant_decider = $this->variantDeciderManager->createInstance(
        $variant_decider_id,
        $settings['variants']['settings'] ?? [],
      );
      assert($variant_decider instanceof AbVariantDeciderInterface);
      $decider_build = $variant_decider->toRenderable(['experimentsSelector' => '[data-ab-tests-entity-root]']);
    }
    catch (PluginException $e) {
      $decider_build = [
        '#attached' => ['library' => ['ab_tests/ab_variant_decider.null']],
      ];
    }
    // Deal with a core bug that won't bubble up attachments correctly.
    $build['#attached'] = NestedArray::mergeDeep($build['#attached'] ?? [], $decider_build['#attached'] ?? []);
    $build['#attached']['drupalSettings']['ab_tests']['debug'] = (bool) ($settings['debug'] ?? FALSE);
    $view_mode = substr($settings['default']['display_mode'] ?? 'node.default', strlen($entity->getEntityTypeId()) + 1);
    $build['#attached']['drupalSettings']['ab_tests']['features']['ab_view_modes']['defaultDecisionValue'] = $view_mode;
    $build['ab_tests_decider'] = $decider_build;
    $classes = $build['#attributes']['class'] ?? [];
    $build['#attributes']['class'] = [...$classes, 'ab-test-loading'];
    $build['#attributes']['data-ab-tests-decider-status'] = 'idle';
    $build['#attributes']['data-ab-tests-feature'] = 'ab_view_modes';
    unset($build['ab_tests_decider']['#attached']);

    $build['#attributes']['data-ab-tests-entity-root'] = $entity->uuid();
  }

  /**
   * Implements hook_preprocess_node().
   *
   * Sets the 'page' variable to TRUE when re-rendering the node via Ajax.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(&$variables): void {
    $route_name = $this->requestStack->getCurrentRequest()
      ->get(RouteObjectInterface::ROUTE_NAME);
    if ($route_name !== 'ab_tests.render_variant') {
      return;
    }
    $entity = $variables['node'] ?? NULL;
    if (!$entity instanceof EntityInterface) {
      return;
    }
    if (!$this->isFullPageEntity($entity)) {
      return;
    }
    // We are dealing with the re-render of the A/B test.
    $variables['page'] = TRUE;
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_node_type_form_alter')]
  public function formNodeTypeFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $type = $form_object->getEntity();
    if (!$type instanceof NodeType) {
      return;
    }

    $settings = $type->getThirdPartySettings('ab_tests')['ab_tests'] ?? [];
    $form['#validate'][] = [$this, 'validatePlugins'];
    $form['ab_tests'] = [
      '#type' => 'details',
      '#title' => $this->t('A/B Tests'),
      '#tree' => TRUE,
    ];
    if (isset($form['additional_settings']) && $form['additional_settings']['#type'] === 'vertical_tabs') {
      $form['ab_tests']['#group'] = 'additional_settings';
    }

    // Add a message about the export config setting if it's enabled.
    $config = $this->configFactory->get('ab_tests.settings');
    $settings_link = Link::createFromRoute($this->t('A/B Tests settings'), 'ab_tests.settings');
    $message = $config->get('ignore_config_export')
      ? $this->t('Note: A/B Tests configuration is currently set to be <strong>ignored</strong> during configuration export. This setting can be changed in the @settings_link.', ['@settings_link' => $settings_link->toString()])
      : $this->t('Note: A/B Tests configuration will be <strong>exported</strong> during configuration export. This setting can be changed in the @settings_link.', ['@settings_link' => $settings_link->toString()]);
    $form['ab_tests']['export_notice'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--status">' . $message . '</div>',
      '#weight' => -10,
    ];

    $form['ab_tests']['is_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable A/B tests'),
      '#description' => $this->t('If enabled, this node type will be used to create A/B tests.'),
      '#default_value' => (bool) ($settings['is_active'] ?? FALSE),
    ];

    $form['ab_tests']['default'] = [
      '#title' => $this->t('Default'),
      '#description' => $this->t('The default version of the A/B test. This is rendered, then hidden from the user. We will unhide this if there is any error with the deciders.'),
      '#description_display' => 'before',
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="ab_tests[is_active]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $view_modes = $this->entityDisplayRepository->getViewModes('node');
    $options = array_combine(
      array_map(static fn(array $view_mode) => $view_mode['id'] ?? '', $view_modes),
      array_map(static fn(array $view_mode) => $view_mode['label'] ?? '', $view_modes),
    );
    // Add the display mode selector to the form.
    $form['ab_tests']['default']['display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Mode'),
      '#description' => $this->t('The entity will be rendered with this display mode while the variant is undecided.'),
      '#options' => [NULL => $this->t('- Select One -'), ...$options],
      '#required' => TRUE,
      '#default_value' => $settings['default']['display_mode'] ?? 'default',
    ];

    $feature = 'ab_view_modes';
    // Use enhanced trait methods to inject plugin selectors with AJAX support.
    $this->injectPluginSelector(
      $form,
      $form_state,
      $settings['variants'] ?? [],
      'variants',
      $feature,
    );
    $this->injectPluginSelector(
      $form,
      $form_state,
      $settings['analytics'] ?? [],
      'analytics',
      $feature,
    );

    $form['#entity_builders'][] = [$this, 'entityBuilder'];
  }

  /**
   * Processes and saves A/B test settings.
   *
   * @param string $entity_type
   *   The machine name of the entity type (e.g., 'node').
   * @param \Drupal\node\NodeTypeInterface $type
   *   The node type entity being processed.
   * @param array $form
   *   The form array where the settings are retrieved from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object that contains the submitted form values.
   */
  public function entityBuilder(string $entity_type, NodeTypeInterface $type, array &$form, FormStateInterface $form_state): void {
    $settings = $form_state->getValue('ab_tests');
    unset($settings['variants'], $settings['analytics']);
    $settings = array_reduce(
      ['variants', 'analytics'],
      fn(array $settings, string $plugin_type_id) => $settings + $this->updatePluginConfiguration(
          $form,
          $form_state,
          $plugin_type_id,
        ),
      $settings,
    );

    // Save the updated settings to the entity.
    $type->setThirdPartySetting(
      module: 'ab_tests',
      key: 'ab_tests',
      value: $settings,
    );
  }

  /**
   * Validates plugin forms for both variant decider and analytics tracker.
   */
  public function validatePlugins(array &$form, FormStateInterface $form_state): void {
    // Skip validation if A/B tests are not active.
    if (!$form_state->getValue(['ab_tests', 'is_active'])) {
      return;
    }

    // Validate the variant decider plugin form.
    $this->validatePluginForm($form, $form_state, 'variants', 'decider');

    // Validate the analytics tracker plugin form.
    $this->validatePluginForm($form, $form_state, 'analytics', 'tracker');
  }

}
