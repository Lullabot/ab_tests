<?php

namespace Drupal\ab_tests\Hook;

use Drupal\ab_tests\AbVariantDeciderInterface;
use Drupal\ab_tests\AbVariantDeciderPluginManager;
use Drupal\ab_tests\EntityHelper;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
   */
  public function __construct(
    private readonly EntityHelper $entityHelper,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    private readonly RequestStack $requestStack,
    private readonly AbVariantDeciderPluginManager $variantDeciderManager,
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
    if (!$this->isFullPageEntity($entity)) {
      return;
    }
    $settings = $this->getSettings($entity);
    $is_active = (bool) ($settings['is_active'] ?? FALSE);
    if (!$is_active) {
      return;
    }
    // Attach the library from the variant resolver.
    $variant_decider_id = $settings['variants']['decider'] ?? 'null';
    try {
      $variant_decider = $this->variantDeciderManager->createInstance(
        $variant_decider_id,
        $settings['variants'][$variant_decider_id],
      );
      assert($variant_decider instanceof AbVariantDeciderInterface);
      $decider_build = $variant_decider->build();
    }
    catch (PluginException $e) {
      $decider_build = [
        '#attached' => ['library' => ['ab_tests/ab_variant_decider_null']],
      ];
    }
    $build['ab_tests_decider'] = $decider_build;
    $build['#attributes']['data-ab-tests-entity-root'] = $entity->uuid();
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
      '#default_value' => $settings['variants']['decider'] ?? NULL,
      '#required' => TRUE,
    ];
    $form = array_reduce(
      $deciders,
      function (array $form, AbVariantDeciderInterface $decider) use ($form_state) {
        assert($decider instanceof PluginFormInterface);
        assert($decider instanceof PluginInspectionInterface);
        $form['ab_tests']['variants'][$decider->getPluginId()] = [
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
          $form,
          $form,
          $form_state
        );
        $settings_form = $decider->buildConfigurationForm($form, $subform_state);
        if (empty($settings_form)) {
          $settings_form = ['#markup' => $this->t('<p>- No configuration options for this decider -</p>')];
        }
        $form['ab_tests']['variants'][$decider->getPluginId()] += $settings_form;
        return $form;
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
    $page_entity = $this->requestStack
      ->getCurrentRequest()
      ->get($entity->getEntityTypeId());
    return $page_entity instanceof EntityInterface && $page_entity->uuid() === $entity->uuid();
  }

}
