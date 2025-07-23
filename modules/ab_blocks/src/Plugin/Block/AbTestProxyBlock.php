<?php

declare(strict_types=1);

namespace Drupal\ab_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
    $config = $this->getConfiguration();

    // Render mode selection.
    $form['render_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Render mode'),
      '#options' => [
        'block' => $this->t('Render selected block'),
        'empty' => $this->t('Render empty (hide block)'),
      ],
      '#default_value' => $config['render_mode'] ?? 'block',
      '#description' => $this->t('Choose whether to render the selected block or hide the block entirely.'),
    ];

    // Target block selection.
    $form['target_block_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Target block'),
      '#options' => ['' => $this->t('- Select a block -')] + $this->getAvailableBlockPlugins(),
      '#default_value' => $config['target_block_plugin'] ?? '',
      '#description' => $this->t('Select the block plugin to proxy through this A/B test block.'),
      '#states' => [
        'visible' => [
          ':input[name="settings[render_mode]"]' => ['value' => 'block'],
        ],
        'required' => [
          ':input[name="settings[render_mode]"]' => ['value' => 'block'],
        ],
      ],
    ];

    // Container for target block configuration (for future enhancement).
    $form['target_block_config_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="settings[render_mode]"]' => ['value' => 'block'],
          ':input[name="settings[target_block_plugin]"]' => ['!value' => ''],
        ],
      ],
    ];

    // Placeholder for future target block configuration embedding.
    $form['target_block_config_container']['info'] = [
      '#type' => 'markup',
      '#markup' => '<p><em>' . $this->t('Target block configuration will be available here in a future enhancement.') . '</em></p>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if ($values['render_mode'] === 'block') {
      if (empty($values['target_block_plugin'])) {
        $form_state->setErrorByName('target_block_plugin',
          $this->t('You must select a target block when render mode is "block".'));
      }

      // Validate that the selected plugin exists.
      if (!empty($values['target_block_plugin']) && !$this->blockManager->hasDefinition($values['target_block_plugin'])) {
        $form_state->setErrorByName('target_block_plugin',
          $this->t('The selected block plugin does not exist.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->configuration['render_mode'] = $values['render_mode'];
    $this->configuration['target_block_plugin'] = $values['target_block_plugin'];

    // Handle target block configuration if needed.
    if ($values['render_mode'] === 'block' && !empty($values['target_block_plugin'])) {
      // For now, store empty configuration. Future enhancement will handle
      // extracting target block configuration from the form.
      $this->configuration['target_block_config'] = $this->extractTargetBlockConfig($form_state);
    }
    else {
      $this->configuration['target_block_config'] = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $config = $this->getConfiguration();

    if ($config['render_mode'] === 'empty') {
      // Return empty render array to hide the block.
      return $build;
    }

    if ($config['render_mode'] === 'block' && !empty($config['target_block_plugin'])) {
      // TODO: Implement block rendering logic in future issue.
      // For now, return a placeholder.
      $build['content'] = [
        '#markup' => $this->t('Proxy block for: @plugin', [
          '@plugin' => $config['target_block_plugin'],
        ]),
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    return $build;
  }

  /**
   * Gets available block plugins for selection.
   *
   * @return array
   *   An array of block plugin options keyed by plugin ID.
   */
  protected function getAvailableBlockPlugins(): array {
    $block_definitions = $this->blockManager->getDefinitions();
    $options = [];

    foreach ($block_definitions as $plugin_id => $definition) {
      // Filter out self and other inappropriate blocks.
      if ($plugin_id !== 'ab_test_proxy_block' && $plugin_id !== 'broken') {
        $options[$plugin_id] = $definition['admin_label'];
      }
    }

    asort($options);
    return $options;
  }

  /**
   * Extracts target block configuration from form state.
   *
   * This is a placeholder for future enhancement when target block
   * configuration embedding is implemented.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The extracted target block configuration.
   */
  protected function extractTargetBlockConfig(FormStateInterface $form_state): array {
    // TODO: Implement target block configuration extraction.
    // This will be enhanced when AJAX form embedding is added.
    return [];
  }

}
