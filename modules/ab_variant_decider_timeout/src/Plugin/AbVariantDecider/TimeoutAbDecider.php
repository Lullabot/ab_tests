<?php

namespace Drupal\ab_variant_decider_timeout\Plugin\AbVariantDecider;

use Drupal\ab_tests\AbVariantDeciderPluginBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ab_variant_decider.
 *
 * @AbVariantDecider(
 *   id = "timeout",
 *   label = @Translation("Timeout"),
 *   description = @Translation("A/B variant decider based on a random timeout."),
 *   decider_library = "ab_variant_decider_timeout/decider_timeout",
 * )
 */
class TimeoutAbDecider extends AbVariantDeciderPluginBase {

  /**
   * Creates a new TimeoutAbDecider object.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $entity_display_repository = $container->get('entity_display.repository');
    return new static($configuration, $plugin_id, $plugin_definition, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'timeout' => ['min' => 0, 'max' => 0],
      'available_variants' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $view_modes = $this->entityDisplayRepository->getViewModes('node');
    $options = array_combine(
      array_map(static fn(array $view_mode) => substr($view_mode['id'] ?? '', 5), $view_modes),
      array_map(static fn(array $view_mode) => $view_mode['label'] ?? '', $view_modes),
    );
    $configuration = $this->getConfiguration();
    return [
      'timeout' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Timeout'),
        '#description' => $this->t('The time it takes to decide a variant randomly. The delay is a random number between the minimum and the maximum. This tries to mock an HTTP request to a 3rd party service.'),
        '#description_display' => 'before',
        'min' => [
          '#type' => 'number',
          '#min' => 0,
          '#title' => $this->t('Minimum'),
          '#description' => $this->t('The minimum time that it can take to decide the variant. In milliseconds.'),
          '#default_value' => $configuration['timeout']['min'],
        ],
        'max' => [
          '#type' => 'number',
          '#min' => 0,
          '#title' => $this->t('Maximum'),
          '#description' => $this->t('The maximum time that it can take to decide the variant. In milliseconds.'),
          '#default_value' => $configuration['timeout']['max'],
        ],
      ],
      'available_variants' => [
        '#title' => $this->t('Available Variants'),
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => $configuration['available_variants'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $minimum = $form_state->getValue(['timeout', 'min']);
    $maximum = $form_state->getValue(['timeout', 'max']);
    if (!is_numeric($minimum) || $minimum < 0) {
      $form_state->setError($form['timeout']['min'], $this->t('The minimum should be a positive number.'));
    }
    if (!is_numeric($maximum) || $maximum < 0) {
      $form_state->setError($form['timeout']['max'], $this->t('The maximum should be a positive number.'));
    }
    if ($minimum > $maximum) {
      $form_state->setError($form['timeout'], $this->t('The minimum cannot be greater than the maximum.'));
    }
    $available_variants = array_filter($form_state->getValue(['available_variants']));
    if (count($available_variants) < 2) {
      $form_state->setError($form['available_variants'], $this->t('Select, at least, two variants for the A/B test.'));
    }
  }

}
