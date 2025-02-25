<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Controller;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for rendering A/B test variants.
 */
final class AbTestsController extends ControllerBase {

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
   * @return \Drupal\Core\Cache\CacheableAjaxResponse
   *   The cacheable AJAX response containing the rendered entity.
   */
  public function renderVariant(string $uuid, string $display_mode): CacheableAjaxResponse {
    // Use a cacheable AJAX response.
    $response = new CacheableAjaxResponse();

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
    $context = new RenderContext();
    $rendered = $this->renderer->executeInRenderContext($context, function () use ($build) {
      return $this->renderer->render($build, TRUE);
    });
    while (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }

    // Create and add the replace command.
    $response->addCommand(
      new ReplaceCommand(
        sprintf('[data-ab-tests-entity-root="%s"]', $uuid),
        $rendered->__toString()
      )
    );

    return $response;
  }

}
