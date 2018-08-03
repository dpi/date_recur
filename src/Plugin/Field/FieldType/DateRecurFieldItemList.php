<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeFieldItemList;

/**
 * Represents a configurable entity date_recur field.
 */
class DateRecurFieldItemList extends DateRangeFieldItemList {
  public function postSave($update) {
    parent::postSave($update);
    /** @var DateRecurItem $item */
    foreach ($this as $field_delta => $item) {
      $item->getOccurrenceHandler()->onSave($update, $field_delta);
    }
    if ($update && isset($field_delta)) {
      $item->getOccurrenceHandler()->onSaveMaxDelta($field_delta);
    }
  }

  public function delete() {
    parent::delete();
    /** @var DateRecurItem $item */
    foreach ($this as $field_delta => $item) {
      $item->getOccurrenceHandler()->onDelete();
    }
  }

  public function deleteRevision() {
    parent::deleteRevision();
    /** @var DateRecurItem $item */
    foreach ($this as $field_delta => $item) {
      $item->getOccurrenceHandler()->onDeleteRevision();
    }
  }
}
