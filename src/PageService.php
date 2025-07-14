<?php

declare(strict_types=1);

namespace Drupal\ab_tests;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\ab_tests\Exceptions\InvalidContextException;

/**
 * Service to deal with the current page.
 */
class PageService {

  /**
   * Crates a new page service.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(
    private RouteMatchInterface $currentRouteMatch,
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Get a node object from the current route object.
   *
   * This method currently has support for the node, node preview and node
   * revision routes specifically. Any other route that specifies a node
   * parameter that is automatically up-cast is also supported.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface|null $route_match
   *   (optional) Route match object, defaults to the current route match
   *   object.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Node object for the current route.
   *
   * @throws \Drupal\ab_tests\Exceptions\InvalidContextException
   */
  public function getNodeFromCurrentRoute(?RouteMatchInterface $route_match = NULL): ?NodeInterface {
    $node = $this->getEntityFromCurrentRoute('node', $route_match);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Get an entity object from the current route object.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Routing\RouteMatchInterface|null $route_match
   *   (optional) Route match object, defaults to the current route match
   *   object.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Node object for the current route.
   *
   * @throws \Drupal\ab_tests\Exceptions\InvalidContextException
   */
  public function getEntityFromCurrentRoute(string $entity_type_id, ?RouteMatchInterface $route_match = NULL): ?EntityInterface {
    if (!$route_match) {
      $route_match = $this->currentRouteMatch;
    }
    $entity = NULL;
    // @todo: turn this into a hook!
    $entity_parameter = $route_match->getParameter($entity_type_id) ?? $route_match->getParameter('current_entity');
    $entity_preview_parameter = $route_match->getParameter($entity_type_id . '_preview');

    if ($entity_parameter) {
      $entity = $entity_parameter;
    }
    if ($entity_preview_parameter) {
      $entity = $entity_preview_parameter;
    }

    if ($entity instanceof EntityInterface) {
      return $entity;
    }
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
    }
    catch (PluginNotFoundException|InvalidPluginDefinitionException $e) {
      throw new InvalidContextException($e->getMessage(), $e->getCode(), $e);
    }
    if (!$storage instanceof RevisionableStorageInterface) {
      return NULL;
    }
    $entity_revision_parameter = $route_match->getParameter($entity_type_id . '_revision');
    $entity = $this->upcastRevision($storage, $entity_revision_parameter);
    if ($entity instanceof EntityInterface) {
      return $entity;
    }
    $uuid = $route_match->getParameter('uuid');
    if (!$uuid) {
      return NULL;
    }
    // Attempt to load the entity when a UUID is provided.
    try {
      return $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);
    }
    catch (EntityStorageException $e) {
      throw new InvalidContextException($e->getMessage(), $e->getCode(), $e);
    }
  }


  /**
   * Upcasts an entity revision parameter.
   *
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $storage
   *   The storage.
   * @param mixed $entity_revision_parameter
   *   The parameter from the route.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity revision if found.
   */
  private function upcastRevision(
    RevisionableStorageInterface $storage,
    mixed $entity_revision_parameter,
  ): ?EntityInterface {
    // The entity revision page does not up-cast the node.
    if (!$entity_revision_parameter || $entity_revision_parameter <= 0) {
      return NULL;
    }
    return $storage->loadRevision($entity_revision_parameter);
  }

}
