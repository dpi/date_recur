<?php

namespace Drupal\date_recur;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Reacts to Drupal entity hooks.
 */
class DateRecurEntityHooks {

  /**
   * Manages config schema type plugins.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Provides the Date recur occurrence handler plugin manager.
   *
   * @var \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManagerInterface
   */
  protected $dateRecurOccurrenceManager;

  /**
   * Constructs a new DateRecurEntityHooks.
   *
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   Manages config schema type plugins.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $dateRecurOccurrenceManager
   *   Provides the Date recur occurrence handler plugin manager.
   */
  public function __construct(TypedDataManagerInterface $typedDataManager, PluginManagerInterface $dateRecurOccurrenceManager) {
    $this->typedDataManager = $typedDataManager;
    $this->dateRecurOccurrenceManager = $dateRecurOccurrenceManager;
  }

  /**
   * Reacts to an inserted field storage config entity.
   *
   * @param \Drupal\field\FieldStorageConfigInterface $fieldStorageConfig
   *   Field storage config.
   *
   * @see \date_recur_field_storage_config_insert()
   *
   * @throws \Exception
   *   Throws exceptions.
   */
  public function fieldStorageConfigInsert(FieldStorageConfigInterface $fieldStorageConfig) {
    $typeDefinition = $this->typedDataManager
      ->getDefinition('field_item:' . $fieldStorageConfig->getType());
    $class = $typeDefinition['class'];

    // Is date_recur or a subclass.
    if (($class == DateRecurItem::class) || (new \ReflectionClass($class))->isSubclassOf(DateRecurItem::class)) {
      $this->getInstanceFromDefinition($fieldStorageConfig)
        ->onFieldCreate($fieldStorageConfig);
    }
  }

  /**
   * Reacts to an updated field storage config entity.
   *
   * @param \Drupal\field\FieldStorageConfigInterface $fieldStorageConfig
   *   Field storage config.
   *
   * @throws \Exception
   *   Throws exceptions.
   *
   * @see \date_recur_field_storage_config_update()
   */
  public function fieldStorageConfigUpdate(FieldStorageConfigInterface $fieldStorageConfig) {
    $typeDefinition = $this->typedDataManager
      ->getDefinition('field_item:' . $fieldStorageConfig->getType());
    $class = $typeDefinition['class'];

    // Is date_recur or a subclass.
    if (($class == DateRecurItem::class) || (new \ReflectionClass($class))->isSubclassOf(DateRecurItem::class)) {
      $this->getInstanceFromDefinition($fieldStorageConfig)
        ->onFieldUpdate($fieldStorageConfig);
    }
  }

  /**
   * Reacts to a deleted field storage config entity.
   *
   * @param \Drupal\field\FieldStorageConfigInterface $fieldStorageConfig
   *   Field storage config.
   *
   * @throws \Exception
   *   Throws exceptions.
   *
   * @see \date_recur_field_storage_config_delete()
   */
  public function fieldStorageConfigDelete(FieldStorageConfigInterface $fieldStorageConfig) {
    $typeDefinition = $this->typedDataManager
      ->getDefinition('field_item:' . $fieldStorageConfig->getType());
    $class = $typeDefinition['class'];

    // Is date_recur or a subclass.
    if (($class == DateRecurItem::class) || (new \ReflectionClass($class))->isSubclassOf(DateRecurItem::class)) {
      $this->getInstanceFromDefinition($fieldStorageConfig)
        ->onFieldDelete($fieldStorageConfig);
    }
  }

  /**
   * Get an occurrence handler for a field definition.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldStorageConfig
   *   Field storage config.
   *
   * @return \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface
   *   A date recur occurrence handler instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  protected function getInstanceFromDefinition(FieldStorageDefinitionInterface $fieldStorageConfig) {
    $pluginName = $fieldStorageConfig->getSetting('occurrence_handler_plugin');
    return $this->dateRecurOccurrenceManager->createInstance($pluginName);
  }

}
