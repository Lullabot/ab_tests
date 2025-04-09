<?php

namespace Drupal\ab_tests\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles AB Tests configuration during import/export operations.
 *
 * Manages preservation of AB Tests third-party settings during configuration
 * transformations based on the module settings.
 */
final class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * Configuration prefixes to ignore.
   *
   * @var array
   */
  const PREFIXES = ['node.type.'];

  /**
   * The active config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected StorageInterface $activeStorage;

  /**
   * The sync config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected StorageInterface $syncStorage;

  /**
   * Ignore ab_tests settings?
   *
   * @var bool
   */
  protected bool $ignoreSettings;

  /**
   * Constructs a new ConfigSubscriber object.
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config active storage.
   * @param \Drupal\Core\Config\StorageInterface $sync_storage
   *   The sync config storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(StorageInterface $config_storage, StorageInterface $sync_storage, ConfigFactoryInterface $config_factory) {
    $this->activeStorage = $config_storage;
    $this->syncStorage = $sync_storage;
    $this->ignoreSettings = (bool) $config_factory->get('ab_tests.settings')->get('ignore_config_export') ?? FALSE;
  }

  /**
   * Processes configuration storage during import transformation.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {
    if (!$this->ignoreSettings) {
      return;
    }
    $transformation_storage = $event->getStorage();
    $destination_storage = $this->activeStorage;

    // If there is configuration in the destination storage (the database)
    // which is not in the transformation storage (from directory) don't delete
    // it from the database.
    $collection_names = [
      StorageInterface::DEFAULT_COLLECTION,
      ...$destination_storage->getAllCollectionNames(),
    ];
    array_map(
      fn (string $collection_name) => $this->onImportTransformCollection(
        $transformation_storage,
        $destination_storage,
        $collection_name,
      ),
      $collection_names,
    );
  }

  /**
   * Preserves AB Tests settings for a specific config collection during import.
   *
   * @param \Drupal\Core\Config\StorageInterface $transformation_storage
   *   The transformation storage.
   * @param \Drupal\Core\Config\StorageInterface $destination_storage
   *   The destination storage.
   * @param string $collection_name
   *   The configuration collection name.
   */
  private function onImportTransformCollection(
    StorageInterface $transformation_storage,
    StorageInterface $destination_storage,
    string $collection_name,
  ) {
    $transformation_collection = $transformation_storage->createCollection($collection_name);
    $destination_collection = $destination_storage->createCollection($collection_name);

    $configuration_names = array_filter(
      $destination_storage->listAll('node.type.'),
      static fn (string $collection_name) => $destination_collection->exists($collection_name) && $transformation_collection->exists($collection_name),
    );
    array_map(
      function (string $config_name) use ($transformation_collection, $destination_collection) {
        // Make sure the config is not removed if it exists.
        $destination_data = $destination_collection->read($config_name);
        if (empty($destination_data['third_party_settings']['ab_tests'])) {
          return;
        }
        $data = $transformation_collection->read($config_name);
        if (!$data) {
          return;
        }
        $data['third_party_settings']['ab_tests'] = $destination_data['third_party_settings']['ab_tests'];
        $transformation_collection->write($config_name, $data);
      },
      $configuration_names,
    );
  }

  /**
   * Processes configuration storage during export transformation.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onExportTransform(StorageTransformEvent $event) {
    if (!$this->ignoreSettings) {
      return;
    }

    $transformation_storage = $event->getStorage();
    $collection_names = [
      StorageInterface::DEFAULT_COLLECTION,
      ...$transformation_storage->getAllCollectionNames(),
    ];
    array_map(
      fn (string $collection_name) => $this->onExportTransformCollection(
        $transformation_storage,
        $collection_name,
      ),
      $collection_names,
    );
  }

  /**
   * Removes AB Tests settings from a specific config collection during export.
   *
   * @param \Drupal\Core\Config\StorageInterface $transformation_storage
   *   The transformation storage.
   * @param string $collection_name
   *   The configuration collection name.
   */
  private function onExportTransformCollection(StorageInterface $transformation_storage, string $collection_name) {
    $transformation_collection = $transformation_storage->createCollection($collection_name);
    $configuration_names = $transformation_collection->listAll('node.type.');
    array_map(
      function (string $config_name) use ($transformation_storage, $transformation_collection) {
        $data = $transformation_storage->read($config_name);
        if (!$data) {
          return;
        }
        if (!isset($data['third_party_settings']['ab_tests'])) {
          return;
        }
        $data = $this->cleanAbTestsThirdPartySettings($data);
        $transformation_collection->write($config_name, $data);
      },
      $configuration_names,
    );
  }

  /**
   * Removes AB Tests third-party settings from configuration data.
   *
   * @param array $data
   *   The configuration data.
   *
   * @return array
   *   The cleaned configuration data.
   */
  private function cleanAbTestsThirdPartySettings(array $data): array {
    // Remove AB Tests third-party settings.
    unset($data['third_party_settings']['ab_tests']);

    // If there are no more third-party settings, remove the array.
    if (empty($data['third_party_settings'])) {
      unset($data['third_party_settings']);
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['onImportTransform'];
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['onExportTransform'];
    return $events;
  }

}
