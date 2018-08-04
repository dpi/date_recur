<?php

namespace Drupal\date_recur\Plugin\DateRecurOccurrenceHandler;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\date_recur\DateRange;
use Drupal\date_recur\Plugin\Field\DateRecurOccurrencesComputed;
use Drupal\date_recur\LegacyDateRecurRRule;
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
   */
  protected $item;

  /**
   * Whether this is a repeating date.
   *
   * @var bool
   */
  protected $isRecurring;

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
    // @todo remove init and move to configuration?
    $this->item = $item;
    $this->isRecurring = !empty($item->rrule);
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
    $startDate = DateRecurUtility::toPhpDateTime($this->item->start_date);
    $startDateEnd = DateRecurUtility::toPhpDateTime($this->item->end_date);
    $startDate->setTimezone($tz);
    $startDateEnd->setTimezone($tz);
    $this->isRecurring = TRUE;
    $this->helper = RlHelper::createInstance($this->item->rrule, $startDate, $startDateEnd);
    return $this->helper;
  }

  /**
   * {@inheritdoc}
   */
  public function humanReadable() {
    if (empty($this->item) || !$this->isRecurring) {
      return '';
    }
    return $this->getHelper()->getRlRuleset()->humanReadable();
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
    $tableName = $this::getOccurrenceCacheStorageTableName($this->item->getFieldDefinition()->getFieldStorageDefinition());

    $entity_id = $this->item->getEntity()->id();
    $field_name = $this->item->getFieldDefinition()->getName();

    if ($this->item->getEntity()->getRevisionId()) {
      $revision_id = $this->item->getEntity()->getRevisionId();
    } else {
      $revision_id = $this->item->getEntity()->id();
    }

    if ($update) {
      $this->database->delete($tableName)
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
    $q = $this->database->insert($tableName)->fields($fields);
    foreach ($rows as $row) {
      $q->values($row);
    }
    $q->execute();
  }

  protected function getOccurrencesForCacheStorage() {
    $storageFormat = $this->item->getDateStorageFormat();
    if (!$this->isRecurring) {
      if (empty($this->item->end_date)) {
        $this->item->end_date = $this->item->start_date;
      }
      return [[
        'value' => LegacyDateRecurRRule::massageDateValueForStorage($this->item->start_date, $storageFormat),
        'end_value' => LegacyDateRecurRRule::massageDateValueForStorage($this->item->end_date, $storageFormat),
      ]];
    }
    else {
      if ($this->getHelper()->isInfinite()) {
        $until = (new \DateTime('now'))
          ->add(new \DateInterval($this->item->getFieldDefinition()->getSetting('precreate')));
      }
      else {
        $until = NULL;
      }

      $occurrences = $this->getHelper()->getOccurrences(NULL, $until);
      return array_map(
        function (DateRange $occurrence) use ($storageFormat) {
          return [
            'value' => LegacyDateRecurRRule::massageDateValueForStorage($occurrence->getStart(), $storageFormat),
            'end_value' => LegacyDateRecurRRule::massageDateValueForStorage($occurrence->getEnd(), $storageFormat),
          ];
        }, $occurrences
      );
    }

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
    $q->condition('entity_id', $this->item->getEntity()->id());
    $q->condition('revision_id', $this->item->getEntity()->getRevisionId());
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
    $q->condition('entity_id', $this->item->getEntity()->id());
    $q->condition('revision_id', $this->item->getEntity()->getRevisionId());
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
    $entity_type = $fieldDefinition->getTargetEntityTypeId();
    $field_name = $fieldDefinition->getName();
    $table_name = $this->getOccurrenceCacheStorageTableName($fieldDefinition);

    $spec = $this->getOccurrenceTableSchema($fieldDefinition);
    $spec['description'] = 'Date recur cache for ' . $entity_type . '.' . $field_name;
    $schema = $this->database->schema();
    $schema->createTable($table_name, $spec);
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
  public function viewsData(FieldStorageDefinitionInterface $fieldDefinition, $data) {
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
