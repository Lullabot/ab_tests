<?php

namespace Drupal\ab_blocks\Form;

use Drupal\ab_tests\Form\PluginSelectionFormTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing block A/B testing.
 */
class ManageBlockTestingForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutBuilderHighlightTrait;
  use LayoutRebuildTrait;
  use PluginSelectionFormTrait;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The section delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * The component uuid.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The Layout Tempstore.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstore;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a new ManageBlockTestingForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The configuration object factory.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ConfigFactory $config_factory) {
    $this->layoutTempstore = $layout_tempstore_repository;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ab_blocks_block_testing_form';
  }

  /**
   * Builds the attributes form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The original delta of the section.
   * @param string $uuid
   *   The UUID of the block being updated.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $uuid = NULL) {
    if (
      !isset($section_storage)
      || !isset($delta)
      || !isset($uuid)
    ) {
      throw new \InvalidArgumentException(__CLASS__ . ' requires all parameters.');
    }

    $this->sectionStorage = $section_storage;
    $this->delta = (int) $delta;
    $this->uuid = $uuid;

    $section = $section_storage->getSection($delta);
    $section_component = $section->getComponent($uuid);
    $settings = $section_component->get('ab_tests');

    $form['#attributes']['data-layout-builder-target-highlight-id'] = $this->blockUpdateHighlightId($uuid);

    $form['#tree'] = TRUE;
    $form['ab_tests']['is_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable A/B tests'),
      '#description' => $this->t('If enabled, this block will be used in A/B tests.'),
      '#default_value' => (bool) ($settings['is_active'] ?? FALSE),
    ];

    $feature = 'ab_blocks';
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

    // Workaround for core bug:
    // https://www.drupal.org/project/drupal/issues/2897377.
    $form['#id'] = Html::cleanCssIdentifier($this->getFormId());

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#button_type' => 'primary',
    ];

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Skip validation if A/B tests are not active.
    if (!$form_state->getValue(['ab_tests', 'is_active'])) {
      return;
    }

    // Validate the variant decider plugin form.
    $this->validatePluginForm($form, $form_state, 'variants', 'decider');

    // Validate the analytics tracker plugin form.
    $this->validatePluginForm($form, $form_state, 'analytics', 'tracker');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $additional_settings = $form_state->getValue('ab_tests');
    unset($additional_settings['variants'], $additional_settings['analytics']);
    $additional_settings = array_reduce(
      ['variants', 'analytics'],
      fn(array $additional_settings, string $plugin_type_id) => $additional_settings + $this->updatePluginConfiguration(
          $form,
          $form_state,
          $plugin_type_id,
        ),
      $additional_settings,
    );

    // Store configuration in layout_builder.component.additional.
    // Switch to third-party settings when
    // https://www.drupal.org/project/drupal/issues/3015152 is committed.
    $delta = $this->getSelectedDelta($form_state);
    $section = $this->sectionStorage->getSection($delta);
    $section->getComponent($this->uuid)
      ->set('ab_tests', $additional_settings);

    $this->layoutTempstore->set($this->sectionStorage);
    $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl());
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

  /**
   * Gets the selected delta.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return int
   *   The section delta.
   */
  protected function getSelectedDelta(FormStateInterface $form_state) {
    if ($form_state->hasValue('region')) {
      return (int) (explode(':', $form_state->getValue('region'))[0] ?? 0);
    }
    return $this->delta;
  }

}
