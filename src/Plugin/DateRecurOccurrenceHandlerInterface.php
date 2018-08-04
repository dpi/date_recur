<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\date_recur\DateRecurHelperInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;

/**
 * Defines an interface for Date recur occurrence handler plugins.
 */
interface DateRecurOccurrenceHandlerInterface extends PluginInspectionInterface {

  /**
   * Init the handler with a field item.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   A field item.
   */
  public function init(DateRecurItem $item);

  /**
   * Get the helper.
   *
   * @return \Drupal\date_recur\DateRecurHelperInterface
   *   The helper.
   */
  public function getHelper();

  /**
   * Does the handler have a recurring date?
   *
   * @return bool
   */
  public function isRecurring();

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

  /**
   * Reacts to field creation.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  public function onFieldCreate(FieldStorageDefinitionInterface $fieldDefinition);

  /**
   * Reacts to field definition update.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  public function onFieldUpdate(FieldStorageDefinitionInterface $fieldDefinition);

  /**
   * Reacts to field deletion.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  public function onFieldDelete(FieldStorageDefinitionInterface $fieldDefinition);

  /**
   * Modify field views data to include occurrences.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $data
   * @return array
   *   The views data.
   */
  public function viewsData(FieldStorageDefinitionInterface $fieldDefinition, $data);

  /**
   * Provides the definition for 'occurrences' property on date_recur fields.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   * @return DataDefinitionInterface
   */
  public static function occurrencePropertyDefinition(FieldStorageDefinitionInterface $field_definition);

}
