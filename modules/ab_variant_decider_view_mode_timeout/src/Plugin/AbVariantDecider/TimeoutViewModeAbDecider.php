<?php

declare(strict_types=1);

namespace Drupal\ab_variant_decider_view_mode_timeout\Plugin\AbVariantDecider;

use Drupal\ab_tests\Plugin\AbVariantDecider\TimeoutAbDeciderBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ab_variant_decider.
 *
 * @AbVariantDecider(
 *   id = "timeout_view_mode",
 *   label = @Translation("Timeout (View Mode)"),
 *   description = @Translation("A/B variant decider based on a random timeout."),
 *   supported_features = {"ab_view_modes"},
 *   decider_library = "ab_tests/ab_variant_decider.timeout",
 * )
 */
class TimeoutViewModeAbDecider extends TimeoutAbDeciderBase {

  /**
   * Creates a new TimeoutViewModeAbDecider object.
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
  protected function timeoutVariantSettingsForm(): array {
    $view_modes = $this->entityDisplayRepository->getViewModes('node');
    $options = array_combine(
      array_map(static fn(array $view_mode) => substr($view_mode['id'] ?? '', 5), $view_modes),
      array_map(static fn(array $view_mode) => $view_mode['label'] ?? '', $view_modes),
    );
    $configuration = $this->getConfiguration();
    return [
      '#title' => $this->t('Available Variants'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $configuration['available_variants'],
    ];
  }

}
