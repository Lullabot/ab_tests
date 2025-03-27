<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Hook;

use Drupal\ab_tests\AbAnalyticsInterface;
use Drupal\ab_tests\AbAnalyticsPluginManager;
use Drupal\ab_tests\AbVariantDeciderInterface;
use Drupal\ab_tests\AbVariantDeciderPluginManager;
use Drupal\ab_tests\EntityHelper;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for ab_tests.
 */
class AbTestsHooks {

  use StringTranslationTrait;

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
   */
  public function __construct(
    private readonly EntityHelper $entityHelper,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    private readonly RequestStack $requestStack,
    private readonly AbVariantDeciderPluginManager $variantDeciderManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AbAnalyticsPluginManager $analyticsManager, private readonly ConfigFactoryInterface $config,
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
      $analytics_tracker_id = $settings['analytics']['tracker'] ?? 'null';
      try {
        $analytics_tracker = $this->analyticsManager->createInstance(
          $analytics_tracker_id,
          $settings['analytics'][$analytics_tracker_id] ?? [],
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
      $build['#attached']['drupalSettings']['ab_tests']['defaultViewMode'] = $settings['default'] ?? 'default';
      $build['ab_tests_tracker'] = $tracker_build;
      unset($build['ab_tests_tracker']['#attached']);
      return;
    }
    if (!$this->isFullPageEntity($entity)) {
      return;
    }
    // Attach the library from the variant resolver.
    $variant_decider_id = $settings['variants']['decider'] ?? 'null';
    try {
      $variant_decider = $this->variantDeciderManager->createInstance(
        $variant_decider_id,
        $settings['variants'][$variant_decider_id] ?? [],
      );
      assert($variant_decider instanceof AbVariantDeciderInterface);
      $decider_build = $variant_decider->toRenderable();
    }
    catch (PluginException $e) {
      $decider_build = [
        '#attached' => ['library' => ['ab_tests/ab_variant_decider.null']],
      ];
    }
    // Deal with a core bug that won't bubble up attachments correctly.
    $build['#attached'] = NestedArray::mergeDeep($build['#attached'] ?? [], $decider_build['#attached'] ?? []);
    $build['#attached']['drupalSettings']['ab_tests']['debug'] = (bool) ($settings['debug'] ?? FALSE);
    $build['#attached']['drupalSettings']['ab_tests']['defaultViewMode'] = $settings['default'] ?? 'default';
    $build['ab_tests_decider'] = $decider_build;
    $classes = $build['#attributes']['class'] ?? [];
    $build['#attributes']['class'] = [...$classes, 'ab-test-loading'];
    unset($build['ab_tests_decider']['#attached']);

    $build['#attributes']['data-ab-tests-entity-root'] = $entity->uuid();
  }

  /**
   * Implements hook_preprocess_node().
   *
   * Sets the 'page' variable to TRUE when re-rendering the node via Ajax.
   */
  #[Hook('hook_preprocess_node')]
  public function preprocessNode(&$variables): void {
    $route_name = $this->requestStack->getCurrentRequest()->get(RouteObjectInterface::ROUTE_NAME);
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
    $form['#validate'][] = [$this, 'validateVariants'];
    $form['ab_tests'] = [
      '#type' => 'details',
      '#title' => $this->t('A/B Tests'),
      '#tree' => TRUE,
    ];
    if (isset($form['additional_settings']) && $form['additional_settings']['#type'] === 'vertical_tabs') {
      $form['ab_tests']['#group'] = 'additional_settings';
    }

    // Add a message about the export config setting if it's enabled.
    $config = \Drupal::config('ab_tests.settings');
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
    $form['ab_tests']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug'),
      '#description' => $this->t('If enabled, debug statements will be printed in the JS console.'),
      '#default_value' => (bool) ($settings['debug'] ?? FALSE),
      '#states' => [
        'visible' => [
          ':input[name="ab_tests[is_active]"]' => ['checked' => TRUE],
        ],
      ],
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

    $form['ab_tests']['variants'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Variants'),
      '#description' => $this->t('Configure the variants of the A/B tests. A variant decider is responsible for deciding which variant to load. This may be a random variant, using a provider like LaunchDarkly, etc.'),
      '#description_display' => 'before',
      '#states' => [
        'visible' => [
          ':input[name="ab_tests[is_active]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    // List all plugin variants.
    $deciders = $this->variantDeciderManager->getDeciders(
      settings: $settings['variants'] ?? []
    );
    $form['ab_tests']['variants']['decider'] = [
      '#type' => 'radios',
      '#title' => $this->t('Variant Decider'),
      '#options' => array_combine(
        array_map(static fn(PluginInspectionInterface $decider) => $decider->getPluginId(), $deciders),
        array_map(static fn(AbVariantDeciderInterface $decider) => $decider->label(), $deciders),
      ),
      '#default_value' => $settings['variants']['decider'] ?? 'null',
      '#required' => TRUE,
    ];
    $form = array_reduce(
      $deciders,
      function (array $form_array, AbVariantDeciderInterface $decider) use ($form_state) {
        assert($decider instanceof PluginFormInterface);
        assert($decider instanceof PluginInspectionInterface);
        $form_array['ab_tests']['variants'][$decider->getPluginId()] = [
          '#type' => 'fieldset',
          '#title' => $decider->label(),
          '#description' => $decider->description(),
          '#description_display' => 'before',
          '#states' => [
            'visible' => [
              ':input[name="ab_tests[variants][decider]"]' => ['value' => $decider->getPluginId()],
            ],
          ],
        ];
        $subform_state = SubformState::createForSubform(
          $form_array,
          $form_array,
          $form_state
        );
        $settings_form = $decider->buildConfigurationForm($form_array, $subform_state);
        if (empty($settings_form)) {
          $settings_form = ['#markup' => $this->t('<p>- No configuration options for this decider -</p>')];
        }
        $form_array['ab_tests']['variants'][$decider->getPluginId()] += $settings_form;
        return $form_array;
      },
      $form,
    );

    $form['ab_tests']['analytics'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Analytics'),
      '#description' => $this->t('Configure the trackers for the A/B tests. An analytics tracker is responsible for recording success / failure for the A/B test. This may send data to Google Analytics, etc.'),
      '#description_display' => 'before',
      '#states' => [
        'visible' => [
          ':input[name="ab_tests[is_active]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    // List all analytics plugins.
    $analytics = $this->analyticsManager->getAnalytics(
      settings: $settings['analytics'] ?? []
    );
    $form['ab_tests']['analytics']['tracker'] = [
      '#type' => 'radios',
      '#title' => $this->t('Analytics Tracker'),
      '#options' => array_combine(
        array_map(static fn(PluginInspectionInterface $analytics) => $analytics->getPluginId(), $analytics),
        array_map(static fn(AbAnalyticsInterface $analytics) => $analytics->label(), $analytics),
      ),
      '#default_value' => $settings['analytics']['tracker'] ?? 'null',
      '#required' => TRUE,
    ];
    $form = array_reduce(
      $analytics,
      function (array $form_array, AbAnalyticsInterface $analytics) use ($form_state) {
        assert($analytics instanceof PluginFormInterface);
        assert($analytics instanceof PluginInspectionInterface);
        $form_array['ab_tests']['analytics'][$analytics->getPluginId()] = [
          '#type' => 'fieldset',
          '#title' => $analytics->label(),
          '#description' => $analytics->description(),
          '#description_display' => 'before',
          '#states' => [
            'visible' => [
              ':input[name="ab_tests[analytics][tracker]"]' => ['value' => $analytics->getPluginId()],
            ],
          ],
        ];
        $subform_state = SubformState::createForSubform(
          $form_array,
          $form_array,
          $form_state
        );
        $settings_form = $analytics->buildConfigurationForm($form_array, $subform_state);
        if (empty($settings_form)) {
          $settings_form = ['#markup' => $this->t('<p>- No configuration options for this analytics plugin -</p>')];
        }
        $form_array['ab_tests']['analytics'][$analytics->getPluginId()] += $settings_form;
        return $form_array;
      },
      $form,
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
    $type->setThirdPartySetting(
      module: 'ab_tests',
      key: 'ab_tests',
      value: $form_state->getValue('ab_tests'),
    );
    $decider_id = $form_state->getValue(['ab_tests', 'variants', 'decider']);
    try {
      $decider = $this->variantDeciderManager->createInstance($decider_id);
      assert($decider instanceof PluginFormInterface);
      $subform_state = SubformState::createForSubform($form['ab_tests']['variants'][$decider_id], $form, $form_state);
      $decider->submitConfigurationForm($form['ab_tests']['variants'][$decider_id], $subform_state);
    }
    catch (PluginException $e) {
    }
  }

  /**
   * Validates the configuration form for the deciders.
   */
  public function validateVariants(array &$form, FormStateInterface $form_state): void {
    $decider_id = $form_state->getValue(['ab_tests', 'variants', 'decider']);
    try {
      $decider = $this->variantDeciderManager->createInstance($decider_id);
      assert($decider instanceof PluginFormInterface);
      $subform_state = SubformState::createForSubform(
        $form['ab_tests']['variants'][$decider_id],
        $form,
        $form_state,
      );
      $decider->validateConfigurationForm($form['ab_tests']['variants'][$decider_id], $subform_state);
    }
    catch (PluginException $e) {
    }
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
    $this->cache[$cid] = $settings;
    return $settings;
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
    $request = $this->requestStack->getCurrentRequest();
    $page_entity = $request
      ->get($entity->getEntityTypeId());
    if ($page_entity instanceof EntityInterface) {
      return $page_entity->uuid() === $entity->uuid();
    }
    $uuid = $request->get('uuid');
    return $uuid === $entity->uuid();
  }

}
