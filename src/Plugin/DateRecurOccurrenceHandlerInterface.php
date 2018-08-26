<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines an interface for Date recur occurrence handler plugins.
 */
interface DateRecurOccurrenceHandlerInterface extends PluginInspectionInterface {

  /**
   * Get the helper.
   *
   * Must always return a helper even if field value is non-recurring.
   *
   * @return \Drupal\date_recur\DateRecurHelperInterface
   *   The helper.
   *
   * @throws \Exception
   *   If a helper could not be created due to faulty field value.
   */
  public function getHelper();

  /**
   * React when a field item is saved.
   *
   * @param bool $update
   *   Whether the save is new (FALSE) or an update (TRUE).
   * @param int $field_delta
   *   The field delta.
   */
  public function onSave($update, $field_delta);

  /**
   * React after a field item list was saved.
   *
   * This is used to clear obsolete deltas.
   *
   * @param int $field_delta
   *   The highest existing field delta.
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
   *   The views data.
   *
   * @return array
   *   The views data.
   */
  public function viewsData(FieldStorageDefinitionInterface $fieldDefinition, array $data);

  /**
   * Provides the definition for 'occurrences' property on date_recur fields.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The data definition.
   */
  public static function occurrencePropertyDefinition(FieldStorageDefinitionInterface $field_definition);

}
