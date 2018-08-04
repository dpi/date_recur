<?php

namespace Drupal\date_recur\Plugin\Field;

use Drupal\Core\TypedData\Plugin\DataType\ItemList;

/**
 * Provides values for the computed 'occurrences' property on date recur fields.
 *
 * Usage:
 * @code
 * $entity->field_myfield->occurrences
 * @endcode
 *
 * @method FieldType\DateRecurItem getParent()
 */
class DateRecurOccurrencesComputed extends ItemList {

  /**
   * {@inheritdoc}
   *
   * @return \Generator
   *   An occurrence generator.
   */
  public function getValue($langcode = NULL) {
    return $this->getParent()
      ->getOccurrenceHandler()
      ->getHelper()
      ->generateOccurrences();
  }

}
