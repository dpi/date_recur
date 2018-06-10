<?php

namespace Drupal\date_recur\Plugin\DateRecurOccurrenceHandler;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\date_recur\DateRecurOccurrencesComputed;
use Drupal\date_recur\DateRecurRRule;
use Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Zend\Stdlib\Exception\InvalidArgumentException;

/**
 * @DateRecurOccurrenceHandler(
 *  id = "date_recur_occurrence_handler",
 *  label = @Translation("Default occurrence handler"),
 * )
 */
class DefaultDateRecurOccurrenceHandler extends PluginBase implements DateRecurOccurrenceHandlerInterface, ContainerFactoryPluginInterface, \Iterator {

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * @var DateRecurItem
   */
  protected $item;

  /**
   * The repeat rule object.
   *
   * @var DateRecurRRule
   */
  protected $rruleObject;

  /**
   * Whether this is a repeating date.
   *
   * @var bool
   */
  protected $isRecurring;

  /**
   * The cache table name.
   *
   * @var string
   */
  protected $tableName;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param Connection $database
   *   The database service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Connection $database
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    // Assume no recurrence until declared otherwise in init().
    $this->isRecurring = FALSE;
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
  public function init(DateRecurItem $item) {
    $this->item = $item;
    if (!empty($item->rrule)) {
      $timezone = new \DateTimeZone($item->timezone);
      $item->start_date->setTimezone($timezone);
      $item->end_date->setTimezone($timezone);
      $this->rruleObject = new DateRecurRRule($item->rrule, $item->start_date, $item->end_date);
      $this->isRecurring = TRUE;
    }
    else {
      $this->isRecurring = FALSE;
    }
    $this->tableName = $this->getOccurrenceTableName($this->item->getFieldDefinition());
  }

  /**
   * {@inheritdoc}
   */
  public function getOccurrencesForDisplay($start = NULL, $end = NULL, $num = NULL) {
    if (empty($this->item) || !$this->isRecurring) {
      return [];
    }
    return $this->rruleObject->getOccurrences($start, $end, $num);
  }

  /**
   * @inheritdoc
   */
  public function getOccurrencesForComputedProperty() {
    return $this->getOccurrencesForDisplay();
  }

  /**
   * {@inheritdoc}
   */
  public function humanReadable() {
    if (empty($this->item) || !$this->isRecurring) {
      return '';
    }
    return $this->rruleObject->humanReadable();
  }

  public function getWeekdays() {
    return $this->rruleObject->getWeekdays();
  }

  /**
   * {@inheritdoc}
   */
  public function isInfinite() {
    if (empty($this->item) || !$this->isRecurring) {
      return 0;
    }
    return (int) $this->rruleObject->isInfinite();
  }

  /**
   * {@inheritdoc}
   */
  public function isRecurring() {
    return $this->isRecurring;
  }

  /**
   * {@inheritdoc}
   */
  public function onSave($update, $field_delta) {
    $entity_id = $this->item->getEntity()->id();
    $field_name = $this->item->getFieldDefinition()->getName();

    if ($this->item->getEntity()->getRevisionId()) {
      $revision_id = $this->item->getEntity()->getRevisionId();
    } else {
      $revision_id = $this->item->getEntity()->id();
    }

    if ($update) {
      $this->database->delete($this->tableName)
        ->condition('entity_id', $entity_id)
        ->condition('field_delta', $field_delta)
        ->execute();
    }

    $fields = ['entity_id', 'revision_id', 'field_delta', $field_name . '_value', $field_name . '_end_value', 'delta'];
    $dates = $this->getOccurrencesForCacheStorage();
    $delta = 0;
    $rows = [];
    foreach ($dates as $date) {
      $rows[] = [
        'entity_id' => $entity_id,
        'revision_id' => $revision_id,
        'field_delta' => $field_delta,
        $field_name . '_value' => $date['value'],
        $field_name . '_end_value' => $date['end_value'],
        'delta' => $delta,
      ];
      $delta++;
    }
    $q = $this->database->insert($this->tableName)->fields($fields);
    foreach ($rows as $row) {
      $q->values($row);
    }
    $q->execute();
  }

  protected function getOccurrencesForCacheStorage() {
    // Get storage format from settings.
    $storageFormat = $this->item->getDateStorageFormat();

    if (!$this->isRecurring) {
      if (empty($this->item->end_date)) {
        $this->item->end_date = $this->item->start_date;
      }
      return [[
        'value' => DateRecurRRule::massageDateValueForStorage($this->item->start_date, $storageFormat),
        'end_value' => DateRecurRRule::massageDateValueForStorage($this->item->end_date, $storageFormat),
      ]];
    }
    else {
      $until = new \DateTime();
      $until->add(new \DateInterval($this->item->getFieldDefinition()->getSetting('precreate')));
      return $this->rruleObject->getOccurrencesForCacheStorage($until, $storageFormat);
    }

  }

  /**
   * @param FieldStorageDefinitionInterface|FieldDefinitionInterface $field
   * @throws InvalidArgumentException
   * @return string
   */
  protected function getOccurrenceTableName($field) {
    if (! ($field instanceof FieldStorageDefinitionInterface || $field instanceof FieldDefinitionInterface)) {
      throw new InvalidArgumentException();
    }
    $entity_type = $field->getTargetEntityTypeId();
    $field_name = $field->getName();
    $table_name = 'date_recur__' . $entity_type . '__' . $field_name;
    return $table_name;
  }

  /**
   * {@inheritdoc}
   */
  public function onSaveMaxDelta($field_delta) {
    $q = $this->database->delete($this->tableName);
    $q->condition('entity_id', $this->item->getEntity()->id());
    $q->condition('revision_id', $this->item->getEntity()->getRevisionId());
    $q->condition('field_delta', $field_delta, '>');
    $q->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete() {
    $table_name = $this->getOccurrenceTableName($this->item->getFieldDefinition());
    $q = $this->database->delete($table_name);
    $q->condition('entity_id', $this->item->getEntity()->id());
    $q->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onDeleteRevision() {
    $table_name = $this->getOccurrenceTableName($this->item->getFieldDefinition());
    $q = $this->database->delete($table_name);
    $q->condition('entity_id', $this->item->getEntity()->id());
    $q->condition('revision_id', $this->item->getEntity()->getRevisionId());
    $q->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldCreate(FieldStorageDefinitionInterface $field) {
    $this->createOccurrenceTable($field);
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldUpdate(FieldStorageDefinitionInterface $field) {
    // Nothing to do.
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDelete(FieldStorageDefinitionInterface $field) {
    $this->dropOccurrenceTable($field);
  }


  protected function createOccurrenceTable(FieldStorageDefinitionInterface $field) {
    $entity_type = $field->getTargetEntityTypeId();
    $field_name = $field->getName();
    $table_name = $this->getOccurrenceTableName($field);

    $spec = $this->getOccurrenceTableSchema($field);
    $spec['description'] = 'Date recur cache for ' . $entity_type . '.' . $field_name;
    $schema = $this->database->schema();
    $schema->createTable($table_name, $spec);
  }

  protected function dropOccurrenceTable(FieldStorageDefinitionInterface $field) {
    $table_name = $this->getOccurrenceTableName($field);
    $schema = $this->database->schema();
    $schema->dropTable($table_name);
  }

  public function getOccurrenceTableSchema(FieldStorageDefinitionInterface $field) {
    $field_name = $field->getName();
    $schema = [
      'fields' => [
        'entity_id' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => "Entity id",
        ],
        'revision_id' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => "Entity revision id",
        ],
        'field_delta' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        'delta' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        $field_name . '_value' => [
          'description' => 'Start date',
          'type' => 'varchar',
          'length' => 20,
        ],
        $field_name . '_end_value' => [
          'description' => 'End date',
          'type' => 'varchar',
          'length' => 20,
        ],
      ],
      'indexes' => [
        'value' => ['entity_id', $field_name . '_value'],
      ],
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function viewsData(FieldStorageDefinitionInterface $field_storage, $data) {
    if (empty($data)) {
      return [];
    }
    $field_name = $field_storage->getName();
    list($table_alias, $revision_table_alias) = array_keys($data);

    // @todo: Revision support.
    unset($data[$revision_table_alias]);
    $recur_table_name = $this->getOccurrenceTableName($field_storage);


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
  public function occurrencePropertyDefinition(FieldStorageDefinitionInterface $field_definition) {
    $occurrence = MapDataDefinition::create()
      ->setPropertyDefinition('value', DataDefinition::create('datetime_iso8601')
        ->setLabel(t('Occurrence start date')))
      ->setPropertyDefinition('end_value', DataDefinition::create('datetime_iso8601')
        ->setLabel(t('Occurrence end date')));

    $occurrences = ListDataDefinition::create('map')
      ->setItemDefinition($occurrence)
      ->setLabel(t('Occurrences'))
      ->setComputed(true)
      ->setClass(DateRecurOccurrencesComputed::class);

    return $occurrences;
  }

  /**
   * Return the current element
   */
  public function current() {
    if (empty($this->rruleObject)) {
      return NULL;
    }
    return $this->rruleObject->current();
  }

  /**
   * Move forward to next element
   */
  public function next() {
    if (!empty($this->rruleObject)) {
      $this->rruleObject->next();
    }
  }

  /**
   * Return the key of the current element
   */
  public function key() {
    if (empty($this->rruleObject)) {
      return NULL;
    }
    return $this->rruleObject->key();
  }

  /**
   * Checks if current position is valid
   */
  public function valid() {
    if (empty($this->rruleObject)) {
      return FALSE;
    }
    return $this->rruleObject->valid();
  }

  /**
   * Rewind the Iterator to the first element
   */
  public function rewind() {
    if (!empty($this->rruleObject)) {
      $this->rruleObject->rewind();
    }
  }
}
