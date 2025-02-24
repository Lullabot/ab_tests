<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Controller;

use Drupal\Component\Plugin\Exception\PluginException;
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
  public function renderVariant(string $uuid, string $display_mode) {
    // @todo Turn this into a cacheable Ajax response.
    $response = new AjaxResponse();
    // Find the entity by UUID.
    try {
      $entities = $this->entityTypeManager()
        ->getStorage('node')
        ->loadByProperties(['uuid' => $uuid]);
    }
    catch (PluginException $e) {
      return $response;
    }

    $entity = reset($entities);
    if (!$entity instanceof EntityInterface) {
      throw new NotFoundHttpException();
    }

    // Build the render array.
    $view_builder = $this->entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($entity, $display_mode);
    $build['#attributes']['data-ab-tests-decision'] = $display_mode;

    // Render the entity.
    $rendered = $this->renderer->renderRoot($build);
    $response->setAttachments($build['#attached']);

    // Create and return an AjaxResponse with ReplaceCommand.
    $response->addCommand(
      new ReplaceCommand(
        sprintf('[data-ab-tests-entity-root="%s"]', $uuid),
        $rendered->__toString()
      )
    );

    return $response;
  }

}
