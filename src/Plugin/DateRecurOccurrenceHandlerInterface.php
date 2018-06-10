<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;

/**
 * Defines an interface for Date recur occurrence handler plugins.
 */
interface DateRecurOccurrenceHandlerInterface extends PluginInspectionInterface {

  /**
   * Init the handler with a field item.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   */
  public function init(DateRecurItem $item);

  /**
   * Does the handler have a recurring date?
   *
   * @return bool
   */
  public function isRecurring();

  /**
   * Does the handler have an infinitely recurring date?
   *
   * @return bool
   */
  public function isInfinite();

  /**
   * Get a list of occurrences for display.
   *
   * Must return an empty array for non-recurring dates.
   * For recurring dates, an array of occurrences must be returned,
   * each defining at least the following keys:
   *  - value - DrupalDateTime
   *  - end_value - DrupalDateTime
   *  Additional keys may be included and may be supported by specific formatters.
   *
   * @param null|\DateTime|DrupalDateTime $start
   * @param null|\DateTime|DrupalDateTime $end
   * @param int $num
   * @return array
   */
  public function getOccurrencesForDisplay($start = NULL, $end = NULL, $num = NULL);

  /**
   * Get a list of occurrences that fits the occurrence property schema.
   *
   * The returned array should match the schema that is returned by
   * occurrencePropertyDefinition().
   *
   * @return array
   */
  public function getOccurrencesForComputedProperty();

  /**
   * Get a human-readable representation of the repeat rule.
   *
   * @return string
   */
  public function humanReadable();

  /**
   * React when a field item is saved.
   *
   * @param bool $update
   * @param int $field_delta
   */
  public function onSave($update, $field_delta);

  /**
   * React after a field item list was saved.
   *
   * This is used to clear obsolete deltas.
   *
   * @param int $field_delta The highest existing field delta.
   */
  public function onSaveMaxDelta($field_delta);

  /**
   * React when a field item is deleted.
   */
  public function onDelete();

  /**
   * React when a field item revision is deleted.
   */
  public function onDeleteRevision();

  public function onFieldCreate(FieldStorageDefinitionInterface $field);

  public function onFieldUpdate(FieldStorageDefinitionInterface $field);

  public function onFieldDelete(FieldStorageDefinitionInterface $field);

  /**
   * Modify field views data to include occurrences.
   *
   * @param \Drupal\field\FieldStorageDefinitionInterface $field_storage
   * @param array $data
   * @return array The views data.
   */
  public function viewsData(FieldStorageDefinitionInterface $field_storage, $data);

  /**
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   * @return DataDefinitionInterface
   */
  public function occurrencePropertyDefinition(FieldStorageDefinitionInterface $field_definition);
}
