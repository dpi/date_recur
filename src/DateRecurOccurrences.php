<?php

namespace Drupal\date_recur;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeListenerInterface;
use Drupal\Core\Field\FieldStorageDefinitionEventSubscriberTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionListenerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\date_recur\Event\DateRecurEvents;
use Drupal\date_recur\Event\DateRecurValueEvent;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Manages occurrences tables and the data that populates them.
 *
 *  - Generates occurrences for tables when entities are modified.
 *  - Manages tables when base or attached date recur fields are created,
 *    modified or deleted.
 */
class DateRecurOccurrences implements EventSubscriberInterface, EntityTypeListenerInterface, FieldStorageDefinitionListenerInterface {

  use EntityTypeEventSubscriberTrait;
  use FieldStorageDefinitionEventSubscriberTrait;

  /**
   * The key in field definitions indicating whether field is date recur like.
   */
  const IS_DATE_RECUR = 'is_date_recur';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Manages data type plugins.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * DateRecurOccurrences constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   Manages data type plugins.
   */
  public function __construct(Connection $database, EntityFieldManagerInterface $entityFieldManager, TypedDataManagerInterface $typedDataManager) {
    $this->database = $database;
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->entityFieldManager = $entityFieldManager;
    $this->typedDataManager = $typedDataManager;
  }

  /**
   * Respond to a field value insertion or update.
   *
   * @param \Drupal\date_recur\Event\DateRecurValueEvent $event
   *   The date recur event.
   */
  public function onSave(DateRecurValueEvent $event) {
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem[]|\Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList $list */
    $list = $event->getField();
    $fieldDefinition = $list->getFieldDefinition();
    $tableName = static::getOccurrenceCacheStorageTableName($fieldDefinition->getFieldStorageDefinition());

    $isInsert = $event->isInsert();
    if (!$isInsert) {
      // Delete all existing values for entity and field combination.
      $entityId = $list->getEntity()->id();
      $this->database->delete($tableName)
        ->condition('entity_id', $entityId)
        ->execute();
    }

    foreach ($list as $item) {
      $this->saveItem($item, $tableName);
    }
  }

  /**
   * Create table rows from occurrences for a single field value.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   Date recur field item.
   * @param string $tableName
   *   The name of table to store occurrences.
   */
  protected function saveItem(DateRecurItem $item, $tableName) {
    $fieldDelta = $item->getName();
    assert(is_int($fieldDelta));
    $fieldName = $item->getFieldDefinition()->getName();
    $entity = $item->getEntity();

    $fields = [
      'entity_id',
      'field_delta',
      'delta',
      $fieldName . '_value',
      $fieldName . '_end_value',
    ];
    $baseRow = [
      'entity_id' => $entity->id(),
      'field_delta' => $fieldDelta,
    ];
    if ($entity->getEntityType()->isRevisionable()) {
      $fields[] = 'revision_id';
      $baseRow['revision_id'] = $entity->getRevisionId();
    }

    $occurrences = $this->getOccurrencesForCacheStorage($item);
    $rows = array_map(
      function (DateRange $occurrence, $delta) use ($baseRow, $fieldName, $item) {
        $row = $baseRow;
        $row['delta'] = $delta;
        $row[$fieldName . '_value'] = $this->massageDateValueForStorage($occurrence->getStart(), $item);
        $row[$fieldName . '_end_value'] = $this->massageDateValueForStorage($occurrence->getEnd(), $item);
        return $row;
      },
      $occurrences,
      array_keys($occurrences)
    );

    $insert = $this->database
      ->insert($tableName)
      ->fields($fields);
    foreach ($rows as $row) {
      $insert->values($row);
    }
    $insert->execute();
  }

  /**
   * Respond to a entity deletion.
   *
   * @param \Drupal\date_recur\Event\DateRecurValueEvent $event
   *   The date recur event.
   */
  public function onEntityDelete(DateRecurValueEvent $event) {
    $list = $event->getField();
    $fieldDefinition = $list->getFieldDefinition();
    $tableName = static::getOccurrenceCacheStorageTableName($fieldDefinition->getFieldStorageDefinition());
    $delete = $this->database
      ->delete($tableName);
    $delete->condition('entity_id', $list->getEntity()->id());
    $delete->execute();
  }

  /**
   * Respond to a entity revision deletion.
   *
   * @param \Drupal\date_recur\Event\DateRecurValueEvent $event
   *   The date recur event.
   */
  public function onEntityRevisionDelete(DateRecurValueEvent $event) {
    $list = $event->getField();
    $entity = $list->getEntity();

    $fieldDefinition = $list->getFieldDefinition();
    $tableName = static::getOccurrenceCacheStorageTableName($fieldDefinition->getFieldStorageDefinition());
    $delete = $this->database->delete($tableName);
    $delete->condition('entity_id', $list->getEntity()->id());
    if ($entity->getEntityType()->isRevisionable()) {
      $delete->condition('revision_id', $entity->getRevisionId());
    }
    $delete->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionCreate(FieldStorageDefinitionInterface $fieldStorageConfig) {
    if ($this->isDateRecur($fieldStorageConfig)) {
      $this->fieldStorageCreate($fieldStorageConfig);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionDelete(FieldStorageDefinitionInterface $fieldStorageConfig) {
    if ($this->isDateRecur($fieldStorageConfig)) {
      $this->fieldStorageDelete($fieldStorageConfig);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeCreate(EntityTypeInterface $entity_type) {
    if (!$entity_type instanceof ContentEntityTypeInterface) {
      // Only add field for content entity types.
      return;
    }

    foreach ($this->getBaseFieldStorages($entity_type) as $baseFieldStorage) {
      $this->fieldStorageCreate($baseFieldStorage);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type) {
    if (!$entity_type instanceof ContentEntityTypeInterface) {
      // Only delete field for content entity types.
      return;
    }

    foreach ($this->getBaseFieldStorages($entity_type) as $baseFieldStorage) {
      $this->fieldStorageDelete($baseFieldStorage);
    }
  }

  /**
   * Reacts to field creation.
   */
  protected function fieldStorageCreate(FieldStorageDefinitionInterface $fieldDefinition) {
    $this->createOccurrenceTable($fieldDefinition);
  }

  /**
   * Reacts to field deletion.
   */
  protected function fieldStorageDelete(FieldStorageDefinitionInterface $fieldDefinition) {
    $tableName = static::getOccurrenceCacheStorageTableName($fieldDefinition);
    $this->database
      ->schema()
      ->dropTable($tableName);
  }

  /**
   * Get all occurrences needing to be stored.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   The date recur field item.
   *
   * @return \Drupal\date_recur\DateRange[]
   *   Date range objects for storage.
   */
  protected function getOccurrencesForCacheStorage(DateRecurItem $item) {
    $until = NULL;
    if ($item->getHelper()->isInfinite()) {
      $until = (new \DateTime('now'))
        ->add(new \DateInterval($item->getFieldDefinition()->getSetting('precreate')));
    }
    return $item->getHelper()->getOccurrences(NULL, $until);
  }

  /**
   * Creates an occurrence table.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  protected function createOccurrenceTable(FieldStorageDefinitionInterface $fieldDefinition) {
    $entityTypeId = $fieldDefinition->getTargetEntityTypeId();
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $fieldName = $fieldDefinition->getName();
    $entityFieldDefinitions = $this->entityFieldManager->getFieldStorageDefinitions($entityTypeId);

    // Logic taken from field tables: see \Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema::getDedicatedTableSchema.
    $idDefinition = $entityFieldDefinitions[$entityType->getKey('id')];
    if ($idDefinition->getType() === 'integer') {
      $fields['entity_id'] = [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'The entity id this data is attached to',
      ];
    }
    else {
      $fields['entity_id'] = [
        'type' => 'varchar_ascii',
        'length' => 128,
        'not null' => TRUE,
        'description' => 'The entity id this data is attached to',
      ];
    }

    if ($entityType->isRevisionable()) {
      $revisionDefinition = $entityFieldDefinitions[$entityType->getKey('revision')];
      if ($revisionDefinition->getType() === 'integer') {
        $fields['revision_id'] = [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'The entity revision id this data is attached to',
        ];
      }
      else {
        $fields['revision_id'] = [
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
          'description' => 'The entity revision id this data is attached to',
        ];
      }
    }

    $fields['field_delta'] = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'The sequence number for this data item, used for multi-value fields',
    ];

    $fields['delta'] = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'The sequence number in generated occurrences for the RRULE',
    ];

    $fieldSchema = $fieldDefinition->getSchema();
    $fields[$fieldName . '_value'] = $fieldSchema['columns']['value'];
    $fields[$fieldName . '_end_value'] = $fieldSchema['columns']['end_value'];

    $schema = [
      'description' => sprintf('Occurrences cache for %s.%s', $fieldDefinition->getTargetEntityTypeId(), $fieldName),
      'fields' => $fields,
      'indexes' => [
        'value' => ['entity_id', $fieldName . '_value'],
      ],
    ];

    $tableName = DateRecurOccurrences::getOccurrenceCacheStorageTableName($fieldDefinition);
    $this->database
      ->schema()
      ->createTable($tableName, $schema);
  }

  /**
   * Convert date ready to be inserted into database column.
   *
   * @param \DateTimeInterface $date
   *   A date time object.
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   The date recur field item.
   *
   * @return string
   *   The date value for storage.
   */
  protected function massageDateValueForStorage(\DateTimeInterface $date, DateRecurItem $item) {
    // Convert native timezone to UTC.
    $date->setTimezone(new \DateTimeZone(DateRecurItem::STORAGE_TIMEZONE));

    // If storage does not allow time, then reset to midday.
    $storageFormat = $item->getDateStorageFormat();
    if ($storageFormat == DateRecurItem::DATE_STORAGE_FORMAT) {
      $date->setTime(12, 0, 0);
    }

    return $date->format($storageFormat);
  }

  /**
   * Determines if a field is date recur or subclasses date recur.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   A field definition.
   *
   * @return bool
   *   Whether field is date recur or subclasses date recur.
   */
  protected function isDateRecur(FieldStorageDefinitionInterface $fieldDefinition) {
    $typeDefinition = \Drupal::service('typed_data_manager')
      ->getDefinition('field_item:' . $fieldDefinition->getType());
    // @see \Drupal\date_recur\DateRecurCachedHooks::fieldInfoAlter
    return isset($typeDefinition[DateRecurOccurrences::IS_DATE_RECUR]);
  }

  /**
   * Get field storage for date recur base fields for an entity type.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entityType
   *   An entity type.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   An array of storage definitions for base fields for an entity type.
   */
  protected function getBaseFieldStorages(ContentEntityTypeInterface $entityType) {
    $baseFields = $this->entityFieldManager->getBaseFieldDefinitions($entityType->id());
    $baseFields = array_filter($baseFields, function (FieldDefinitionInterface $fieldDefinition) {
      return $this->isDateRecur($fieldDefinition->getFieldStorageDefinition());
    });

    return array_map(
      function (FieldDefinitionInterface $baseField) {
        return $baseField->getFieldStorageDefinition();
      },
      $baseFields
    );
  }

  /**
   * Get the name of the table containing occurrences for a field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return string
   *   A table name.
   */
  public static function getOccurrenceCacheStorageTableName(FieldStorageDefinitionInterface $fieldDefinition) {
    return sprintf('date_recur__%s__%s', $fieldDefinition->getTargetEntityTypeId(), $fieldDefinition->getName());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      DateRecurEvents::FIELD_VALUE_SAVE => ['onSave'],
      DateRecurEvents::FIELD_ENTITY_DELETE => ['onEntityDelete'],
      DateRecurEvents::FIELD_REVISION_DELETE => ['onEntityRevisionDelete'],
    ] + static::getEntityTypeEvents() + static::getFieldStorageDefinitionEvents();
  }

}
