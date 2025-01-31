<?php

namespace Drupal\ab_tests\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;

/**
 * Controller for rendering A/B test variants.
 */
class AbTestsController extends ControllerBase {

  /**
   * Constructs an AbTestsController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Renders an entity with the specified display mode.
   *
   * @param string $uuid
   *   The UUID of the entity to render.
   * @param string $display_mode
   *   The display mode to use.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The AJAX response containing the rendered entity.
   */
  public function renderVariant($uuid, $display_mode) {
    // Find the entity by UUID.
    $entities = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['uuid' => $uuid]);

    $entity = reset($entities);
    if (!$entity instanceof EntityInterface) {
      throw new NotFoundHttpException();
    }

    // Build the render array.
    $view_builder = $this->entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($entity, $display_mode);

    // Render the entity.
    $rendered = $this->renderer->renderRoot($build);

    // Create and return an AjaxResponse with ReplaceCommand.
    // @todo Turn this into a cacheabe JSON response.
    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand(
        sprintf('[data-ab-tests-entity-root="%s"]', $uuid),
        $rendered->__toString()
      )
    );

    return $response;
  }

} 