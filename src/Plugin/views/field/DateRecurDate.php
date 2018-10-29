<?php

namespace Drupal\date_recur\Plugin\views\field;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\views\Plugin\views\field\Date;
use Drupal\views\ResultRow;

/**
 * Date field.
 *
 * Extends core date field plugin permitting alternative source date format.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("date_recur_date")
 * @property \Drupal\views\Plugin\views\query\Sql query
 */
class DateRecurDate extends Date {

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $value = parent::getValue($values, $field);

    assert(isset($this->configuration['source date format']));
    $sourceDateFormat = $this->configuration['source date format'];

    // @todo configurable timezones?
    $timeZone = new \DateTimeZone('UTC');
    $date = DrupalDateTime::createFromFormat($sourceDateFormat, $value, $timeZone);

    $timestamp = $date->getTimestamp();
    return $timestamp;
  }

}
