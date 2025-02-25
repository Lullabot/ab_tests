<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides helper methods for entity management.
 */
class EntityHelper {

  /**
   * Constructor method for the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager instance.
   *
   * @return void
   */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Gets the bundle information of any entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object (e.g., NodeInterface, UserInterface, etc.)
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface|null
   *   The bundle configuration entity (e.g. NodeType, TaxonomyVocabulary,
   *   etc.), or NULL if it cannot be determined.
   */
  public function getBundle(EntityInterface $entity): ?ConfigEntityInterface {
    // Get the entity type and bundle information.
    $entity_type = $entity->getEntityType();
    $bundle_key = $entity_type->getKey('bundle');

    // Check if the entity has a bundle key.
    if (!$bundle_key) {
      return NULL;
    }
    // Use the entity type manager to load the bundle's configuration entity.
    $bundle_entity_type_id = $entity_type->getBundleEntityType();
    if (!$bundle_entity_type_id) {
      return NULL;
    }
    // Load and return the bundle configuration entity.
    try {
      $bundle_entity = $this->entityTypeManager
        ->getStorage($bundle_entity_type_id)
        ->load($entity->bundle());
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return NULL;
    }

    return $bundle_entity instanceof ConfigEntityInterface
      ? $bundle_entity
      : NULL;
  }

}
