<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for A/B Tests.
 */
final class AbTestsSettingsForm extends ConfigFormBase {

  /**
   * Constructs a new AbTestsSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ab_tests.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ab_tests_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ab_tests.settings');
    $config_filter_installed = $this->moduleHandler->moduleExists('config_filter');

    // Default to FALSE if config_filter is not installed.
    $default_value = $config_filter_installed ? ($config->get('ignore_config_export') ?? FALSE) : FALSE;

    $form['ignore_config_export'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore A/B Tests configuration during configuration export'),
      '#description' => $this->t('When enabled, third-party settings for A/B Tests will not be included in the configuration export.'),
      '#default_value' => $default_value,
      '#disabled' => !$config_filter_installed,
    ];

    if (!$config_filter_installed) {
      $config_filter_url = Url::fromUri(
        'https://www.drupal.org/project/config_filter', [
          'attributes' => ['target' => '_blank'],
        ]
      );
      $config_filter_link = Link::fromTextAndUrl('Config Filter', $config_filter_url)
        ->toString();

      $form['config_filter_notice'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t(
            'The @config_filter module is required to ignore A/B Tests settings in the configuration export. Install the module to enable this feature.', [
              '@config_filter' => $config_filter_link,
            ]
          ) . '</div>',
        '#weight' => -10,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Only save the setting if config_filter is installed.
    if ($this->moduleHandler->moduleExists('config_filter')) {
      $this->config('ab_tests.settings')
        ->set('ignore_config_export', $form_state->getValue('ignore_config_export'))
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
