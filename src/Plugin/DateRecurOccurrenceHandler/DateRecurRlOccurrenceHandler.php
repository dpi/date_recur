<?php

namespace Drupal\date_recur\Plugin\DateRecurOccurrenceHandler;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\date_recur\DateRange;
use Drupal\date_recur\DateRecurNonRecurringHelper;
use Drupal\date_recur\Plugin\Field\DateRecurOccurrencesComputed;
use Drupal\date_recur\DateRecurUtility;
use Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\date_recur\Rl\RlHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the default occurrence handler.
 *
 * @DateRecurOccurrenceHandler(
 *  id = "date_recur_occurrence_handler",
 *  label = @Translation("Default occurrence handler"),
 * )
 *
 * @ingroup RLanvinPhpRrule
 */
class DateRecurRlOccurrenceHandler extends PluginBase implements DateRecurOccurrenceHandlerInterface, ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date_recur field item.
   *
   * @todo convert all usages to function, as item is not always set!
   *
   * handler can be used in item context, or outside.
   *
   * throw exception if no item.
   *
   * Else if item is not actually required, rework args or make static.
   *
   * @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem
   *   The field item.
   */
  protected $item;

  /**
   * Construct a new DateRecurRlOccurrenceHandler.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->item = isset($configuration['field_item']) ? $configuration['field_item'] : NULL;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getHelper() {
    if (isset($this->helper)) {
      return $this->helper;
    }

    if (!isset($this->item)) {
      throw new \Exception('Field item not set.');
    }

    // Set the timezone to the same as the source timezone for convenience.
    $tz = new \DateTimeZone($this->item->timezone);

    $startDate = NULL;
    $startDateEnd = NULL;
    if ($this->item->start_date instanceof DrupalDateTime) {
      $startDate = DateRecurUtility::toPhpDateTime($this->item->start_date);
      $startDate->setTimezone($tz);
    }
    else {
      throw new \Exception('Start date is required.');
    }
    if ($this->item->end_date instanceof DrupalDateTime) {
      $startDateEnd = DateRecurUtility::toPhpDateTime($this->item->end_date);
      $startDateEnd->setTimezone($tz);
    }
    $this->helper = $this->item->isRecurring() ?
      RlHelper::createInstance($this->item->rrule, $startDate, $startDateEnd) :
      DateRecurNonRecurringHelper::createInstance('', $startDate, $startDateEnd);
    return $this->helper;
  }

  /**
   * {@inheritdoc}
   */
  public function onSave($update, $field_delta) {
    $tableName = $this::getOccurrenceCacheStorageTableName($this->item->getFieldDefinition()->getFieldStorageDefinition());

    $entity = $this->item->getEntity();
    $entityIsRevisionable = $entity instanceof RevisionableInterface;
    $entity_id = $entity->id();
    $field_name = $this->item->getFieldDefinition()->getName();

    if ($update) {
      $this->database->delete($tableName)
        ->condition('entity_id', $entity_id)
        ->condition('field_delta', $field_delta)
        ->execute();
    }

    $fields = [
      'entity_id',
      'field_delta',
      $field_name . '_value',
      $field_name . '_end_value',
      'delta',
    ];
    if ($entityIsRevisionable) {
      $fields[] = 'revision_id';
    }

    $occurrences = $this->getOccurrencesForCacheStorage();
    $delta = 0;
    $rows = [];
    foreach ($occurrences as $delta => $occurrence) {
      $value = $this->massageDateValueForStorage($occurrence->getStart());
      $endValue = $this->massageDateValueForStorage($occurrence->getEnd());
      $row = [
        'entity_id' => $entity_id,
        'field_delta' => $field_delta,
        $field_name . '_value' => $value,
        $field_name . '_end_value' => $endValue,
        'delta' => $delta,
      ];
      if ($entityIsRevisionable) {
        /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
        $row['revision_id'] = $entity->getRevisionId();
      }
      $rows[] = $row;
    }
    $q = $this->database->insert($tableName)->fields($fields);
    foreach ($rows as $row) {
      $q->values($row);
    }
    $q->execute();
  }

  /**
   * Get occurrences for storage.
   *
   * @return \Drupal\date_recur\DateRange[]
   *   Date range objects.
   *
   * @internal
   */
  protected function getOccurrencesForCacheStorage() {
    $until = NULL;
    if ($this->getHelper()->isInfinite()) {
      $until = (new \DateTime('now'))
        ->add(new \DateInterval($this->item->getFieldDefinition()->getSetting('precreate')));
    }
    return $this->getHelper()->getOccurrences(NULL, $until);
  }

  protected function massageDateValueForStorage(\DateTime $date) {
    $date->setTimezone(new \DateTimeZone(DateRecurItem::STORAGE_TIMEZONE));

    $storageFormat = $this->item->getDateStorageFormat();
    if ($storageFormat == DateRecurItem::DATE_STORAGE_FORMAT) {
      $date->setTime(12, 0, 0);
    }

    return $date->format($storageFormat);
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
  public function onSaveMaxDelta($field_delta) {
    $tableName = $this::getOccurrenceCacheStorageTableName($this->item->getFieldDefinition()->getFieldStorageDefinition());
    $q = $this->database->delete($tableName);
    $entity = $this->item->getEntity();
    $q->condition('entity_id', $entity->id());
    if ($entity instanceof RevisionableInterface) {
      $q->condition('revision_id', $entity->getRevisionId());
    }
    $q->condition('field_delta', $field_delta, '>');
    $q->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete() {
    $table_name = $this->getOccurrenceCacheStorageTableName($this->item->getFieldDefinition()->getFieldStorageDefinition());
    $q = $this->database->delete($table_name);
    $q->condition('entity_id', $this->item->getEntity()->id());
    $q->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onDeleteRevision() {
    $table_name = $this->getOccurrenceCacheStorageTableName($this->item->getFieldDefinition()->getFieldStorageDefinition());
    $q = $this->database->delete($table_name);
    $entity = $this->item->getEntity();
    $q->condition('entity_id', $entity->id());
    if ($entity instanceof RevisionableInterface) {
      $q->condition('revision_id', $entity->getRevisionId());
    }
    $q->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldCreate(FieldStorageDefinitionInterface $fieldDefinition) {
    $this->createOccurrenceTable($fieldDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldUpdate(FieldStorageDefinitionInterface $fieldDefinition) {
    // Nothing to do.
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDelete(FieldStorageDefinitionInterface $fieldDefinition) {
    $tableName = $this->getOccurrenceCacheStorageTableName($fieldDefinition);
    $this->database
      ->schema()
      ->dropTable($tableName);
  }

  /**
   * Creates an occurrence table.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  protected function createOccurrenceTable(FieldStorageDefinitionInterface $fieldDefinition) {
    $fields['entity_id'] = [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => "Entity id",
    ];

    if ($fieldDefinition->isRevisionable()) {
      $fields['revision_id'] = [
        'type' => 'int',
        'not null' => FALSE,
        'default' => NULL,
        'description' => "Entity revision id",
      ];
    }

    $fields['field_delta'] = [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
    ];
    $fields['delta'] = [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
    ];

    $fieldName = $fieldDefinition->getName();
    $fields[$fieldName . '_value'] = [
      'description' => 'Start date',
      'type' => 'varchar',
      'length' => 20,
    ];
    $fields[$fieldName . '_end_value'] = [
      'description' => 'End date',
      'type' => 'varchar',
      'length' => 20,
    ];

    $schema = [
      'description' => sprintf('Date recur cache for %s.%s', $fieldDefinition->getTargetEntityTypeId(), $fieldName),
      'fields' => $fields,
      'indexes' => [
        'value' => ['entity_id', $fieldName . '_value'],
      ],
    ];

    $table_name = $this->getOccurrenceCacheStorageTableName($fieldDefinition);
    $this->database
      ->schema()
      ->createTable($table_name, $schema);
  }

  /**
   * {@inheritdoc}
   */
  public function viewsData(FieldStorageDefinitionInterface $fieldDefinition, array $data) {
    if (empty($data)) {
      return [];
    }
    $field_name = $fieldDefinition->getName();
    list($table_alias, $revision_table_alias) = array_keys($data);

    // @todo: Revision support.
    unset($data[$revision_table_alias]);
    $recur_table_name = $this->getOccurrenceCacheStorageTableName($fieldDefinition);

    $field_table = $data[$table_alias];
    $recur_table = $field_table;

    $join_key = array_keys($field_table['table']['join'])[0];
    $recur_table['table']['join'] = $field_table['table']['join'];
    $recur_table['table']['join'][$join_key]['table'] = $recur_table_name;
    $recur_table['table']['join'][$join_key]['extra'] = [];

    // Update table name references.
    $handler_keys = ['argument', 'filter', 'sort', 'field'];
    foreach ($recur_table as $column_name => &$column_data) {
      if ($column_name == 'table') {
        continue;
      }
      if (!$this->viewsDataCheckIfMoveColumnName($field_name, $column_name, $column_data)) {
        unset($recur_table[$column_name]);
      }
      else {
        unset($field_table[$column_name]);
        foreach ($handler_keys as $key) {
          if (!empty($column_data[$key]['table'])) {
            $column_data[$key]['table'] = $recur_table_name;
            $column_data[$key]['additional fields'] = [
              $field_name . '_value',
              $field_name . '_end_value',
              'delta',
              'field_delta'
            ];
          }
        }
        if ($column_name == $field_name . '_value') {
          $column_data['field']['click sortable'] = TRUE;
        }
      }
    }

    $custom_handler_name = $field_name . '_simple_render';
    $recur_table[$custom_handler_name] = $recur_table[$field_name];
    $recur_table[$custom_handler_name]['title'] .= $this->t(' (simple render)');
    $recur_table[$custom_handler_name]['field']['id'] = 'date_recur_field_simple_render';

    $return_data = [$recur_table_name => $recur_table, $table_alias => $field_table];
    return $return_data;
  }

  protected function viewsDataCheckIfMoveColumnName($fieldName, $columnName, $columnData) {
    $fieldsToMove = [
      $fieldName,
      $fieldName . '_value',
      $fieldName . '_end_value',
    ];
    if (in_array($columnName, $fieldsToMove)) {
      return TRUE;
    }
    else if (strpos($columnName, $fieldName . '_value') === 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function occurrencePropertyDefinition(FieldStorageDefinitionInterface $field_definition) {
    $occurrences = ListDataDefinition::create('any')
//      ->setItemDefinition($occurrence)
      ->setLabel(new TranslatableMarkup('Occurrences'))
      ->setComputed(TRUE)
      ->setClass(DateRecurOccurrencesComputed::class);

    return $occurrences;
  }

}
