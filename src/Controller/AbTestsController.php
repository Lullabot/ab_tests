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
use Psr\Log\LoggerInterface;
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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('logger.factory')->get('ab_tests'),
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
      $this->logError('Entity loading failed for UUID @uuid: @message', [
        '@uuid' => $uuid,
        '@message' => $e->getMessage(),
      ]);
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
    $build['#attributes']['data-ab-tests-decider-status'] = 'success';

    // Render the entity.
    $context = new RenderContext();
    try {
      $rendered = $this->renderer->executeInRenderContext($context, function () use ($build) {
        return $this->renderer->render($build, TRUE);
      });
    }
    catch (\Exception $e) {
      $this->logError('Entity rendering failed for UUID @uuid with display mode @display_mode: @message', [
        '@uuid' => $uuid,
        '@display_mode' => $display_mode,
        '@message' => $e->getMessage(),
      ]);
      return $response;
    }
    // Add the assets, libraries, settings, and cache information bubbled up
    // during rendering.
    while (!$context->isEmpty()) {
      $metadata = $context->pop();
      $response->addAttachments($metadata->getAttachments());
      $response->addCacheableDependency($metadata);
    }

    // Create and add the replace command.
    $response->addCommand(
      new ReplaceCommand(
        sprintf('[data-ab-tests-instance-id="%s"]', $uuid),
        $rendered->__toString()
      )
    );

    return $response;
  }

  /**
   * Logs an error message if debug mode is enabled.
   *
   * @param string $message
   *   The message to log.
   * @param array $variables
   *   Array of variables to replace in the message.
   */
  private function logError(string $message, array $variables = []): void {
    if (!$this->configFactory->get('ab_tests.settings')->get('debug_mode')) {
      return;
    }

    $this->logger->error($message, $variables);
  }

}
