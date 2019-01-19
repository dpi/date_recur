<?php

namespace Drupal\date_recur\Plugin\Field;

use Drupal\datetime\DateTimeComputed;

/**
 * A computed property for dates of date time field items.
 *
 * Overrides core class to modify time zone.
 */
class DateRecurDateTimeComputed extends DateTimeComputed {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $hasValueBefore = isset($this->date);
    parent::getValue();
    if (!$hasValueBefore && isset($this->date)) {
      /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item */
      $item = $this->getParent();
      $this->date->setTimezone(new \DateTimeZone($item->timezone));
    }
    return $this->date;
  }

}
