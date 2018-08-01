<?php

namespace Drupal\date_recur;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provide standalone utilities.
 */
class DateRecurUtility {

  /**
   * Downgrades a DrupalDateTime object to PHP date time.
   *
   * Useful in situations where object comparison is used.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $drupalDateTime
   *   A Drupal datetime object.
   *
   * @see https://www.drupal.org/node/2936388
   *
   * @return \Datetime
   *   A PHP datetime object.
   *
   * @deprecated remove when at least Drupal 8.6 is minimum supported version.
   *   Use \Drupal\Core\Datetime\DrupalDateTime::getPhpDateTime() instead.
   *
   * @internal
   */
  public static function toPhpDateTime(DrupalDateTime $drupalDateTime) {
    return new \Datetime($drupalDateTime->format('r'), $drupalDateTime->getTimezone());
  }

}
