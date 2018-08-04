<?php

namespace Drupal\date_recur\EventSubscriber;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeListenerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to changes on entity types.
 */
class DateRecurEntitySchemaSubscriber implements EntityTypeListenerInterface, EventSubscriberInterface {

  use EntityTypeEventSubscriberTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The occurrence plugin manager.
   *
   * @var \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManager
   */
  protected $dateRecurOccurrenceHandlerManager;

  /**
   * Constructs a ViewsEntitySchemaSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManager $dateRecurOccurrenceHandlerManager
   *   The occurrence plugin manager.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, DateRecurOccurrenceHandlerManager $dateRecurOccurrenceHandlerManager) {
    $this->entityFieldManager = $entityFieldManager;
    $this->dateRecurOccurrenceHandlerManager = $dateRecurOccurrenceHandlerManager;
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeCreate(EntityTypeInterface $entity_type) {
    if (!$entity_type instanceof ContentEntityTypeInterface) {
      // Only add field for content entity types.
      return;
    }

    $baseFields = $this->entityFieldManager->getBaseFieldDefinitions($entity_type->id());
    $baseFields = array_filter($baseFields, function (FieldDefinitionInterface $fieldDefinition) {
      return 'date_recur' === $fieldDefinition->getType();
    });
    foreach ($baseFields as $baseField) {
      $fieldStorage = $baseField->getFieldStorageDefinition();
      $pluginName = $fieldStorage->getSetting('occurrence_handler_plugin');
      /** @var \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface $plugin */
      $plugin = $this->dateRecurOccurrenceHandlerManager
        ->createInstance($pluginName);
      $plugin->onFieldCreate($fieldStorage);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type) {
    if (!$entity_type instanceof ContentEntityTypeInterface) {
      // Only add field for content entity types.
      return;
    }

    $baseFields = $this->entityFieldManager->getBaseFieldDefinitions($entity_type->id());
    $baseFields = array_filter($baseFields, function (FieldDefinitionInterface $fieldDefinition) {
      return 'date_recur' === $fieldDefinition->getType();
    });
    foreach ($baseFields as $baseField) {
      $fieldStorage = $baseField->getFieldStorageDefinition();
      $pluginName = $fieldStorage->getSetting('occurrence_handler_plugin');
      /** @var \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface $plugin */
      $plugin = $this->dateRecurOccurrenceHandlerManager
        ->createInstance($pluginName);
      $plugin->onFieldDelete($fieldStorage);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return static::getEntityTypeEvents();
  }

}
