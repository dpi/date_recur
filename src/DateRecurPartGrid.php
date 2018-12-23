<?php

namespace Drupal\date_recur;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\date_recur\Exception\DateRecurRulePartIncompatible;

/**
 * Frequency/part support grid.
 */
class DateRecurPartGrid {

  // @todo move constants to new class.

  /**
   * Parts.
   *
   * In no particular order.
   *
   * @internal will be made protected when PHP7.1 is minimum.
   */
  const PARTS = [
    'DTSTART',
    'UNTIL',
    'COUNT',
    'INTERVAL',
    'BYSECOND',
    'BYMINUTE',
    'BYHOUR',
    'BYDAY',
    'BYMONTHDAY',
    'BYYEARDAY',
    'BYWEEKNO',
    'BYMONTH',
    'BYSETPOS',
    'WKST',
  ];

  /**
   * Frequencies.
   *
   * In no particular order.
   *
   * @internal will be made protected when PHP7.1 is minimum.
   */
  const FREQUENCIES = [
    'SECONDLY',
    'MINUTELY',
    'HOURLY',
    'DAILY',
    'WEEKLY',
    'MONTHLY',
    'YEARLY',
  ];

  /**
   * Incompatible parts.
   *
   * Specifies parts which are incompatible with frequencies.
   *
   * @internal will be made protected when PHP7.1 is minimum.
   * @see https://tools.ietf.org/html/rfc5545#page-44
   */
  const INCOMPATIBLE_PARTS = [
    'SECONDLY' => ['BYWEEKNO'],
    'MINUTELY' => ['BYWEEKNO'],
    'HOURLY' => ['BYWEEKNO'],
    'DAILY' => ['BYWEEKNO', 'BYYEARDAY'],
    'WEEKLY' => ['BYWEEKNO', 'BYYEARDAY', 'BYMONTHDAY'],
    'MONTHLY' => ['BYWEEKNO', 'BYYEARDAY'],
    'YEARLY' => [],
  ];

  /**
   * Supported parts for this part grid.
   *
   * @var array
   *   Parts keyed by frequency.
   */
  protected $supportedParts = [];

  /**
   * Labels for frequencies.
   *
   * @return array
   *   Labels for frequencies keyed by frequency.
   */
  public static function frequencyLabels() {
    return [
      'SECONDLY' => new TranslatableMarkup('Secondly'),
      'MINUTELY' => new TranslatableMarkup('Minutely'),
      'HOURLY' => new TranslatableMarkup('Hourly'),
      'DAILY' => new TranslatableMarkup('Daily'),
      'WEEKLY' => new TranslatableMarkup('Weekly'),
      'MONTHLY' => new TranslatableMarkup('Monthly'),
      'YEARLY' => new TranslatableMarkup('Yearly'),
    ];
  }

  /**
   * Labels for parts.
   *
   * @return array
   *   Labels for parts keyed by part.
   */
  public static function partLabels() {
    return [
      'DTSTART' => new TranslatableMarkup('Start date'),
      'UNTIL' => new TranslatableMarkup('Until'),
      'COUNT' => new TranslatableMarkup('Count'),
      'INTERVAL' => new TranslatableMarkup('Interval'),
      'BYSECOND' => new TranslatableMarkup('By-second'),
      'BYMINUTE' => new TranslatableMarkup('By-minute'),
      'BYHOUR' => new TranslatableMarkup('By-hour'),
      'BYDAY' => new TranslatableMarkup('By-day'),
      'BYMONTHDAY' => new TranslatableMarkup('By-day-of-month'),
      'BYYEARDAY' => new TranslatableMarkup('By-day-of-year'),
      'BYWEEKNO' => new TranslatableMarkup('By-week-number'),
      'BYMONTH' => new TranslatableMarkup('By-month'),
      'BYSETPOS' => new TranslatableMarkup('By-set-position'),
      'WKST' => new TranslatableMarkup('Week start'),
    ];
  }

  /**
   * Descriptions for parts.
   *
   * @return array
   *   Descriptions for parts keyed by part.
   */
  public static function partDescriptions() {
    return [
      'DTSTART' => new TranslatableMarkup('The starting date.'),
      'UNTIL' => new TranslatableMarkup('Specify a date occurrences cannot be generated past.'),
      'COUNT' => new TranslatableMarkup('Specify number of occurrences.'),
      'INTERVAL' => new TranslatableMarkup('Specify at an interval where the repeating rule repeats.'),
      'BYSECOND' => new TranslatableMarkup('Specify the second(s) where a repeating rule repeats.'),
      'BYMINUTE' => new TranslatableMarkup('Specify the minute(s) where a repeating rule repeats.'),
      'BYHOUR' => new TranslatableMarkup('Specify the hour(s) where a repeating rule repeats.'),
      'BYDAY' => new TranslatableMarkup('Specify the weekday(s) where a repeating rule repeats.'),
      'BYMONTHDAY' => new TranslatableMarkup('Specify the day number(s) in a month where a repeating rule repeats.'),
      'BYYEARDAY' => new TranslatableMarkup('Specify the day number(s) in a year where a repeating rule repeats.'),
      'BYWEEKNO' => new TranslatableMarkup('Specify the week number(s) in a year where a repeating rule repeats.'),
      'BYMONTH' => new TranslatableMarkup('Specify the month(s) where a repeating rule repeats.'),
      'BYSETPOS' => new TranslatableMarkup('Specify the the nth occurrence(s) in combination with other BY rules to to limit occurrences.'),
      'WKST' => new TranslatableMarkup('Specify the first day of the week.'),
    ];
  }

  public static function configSettingsToGrid(array $parts) {
    $grid = new static();

    if (!empty($parts['all'])) {
      return $grid;
    }

    $frequencies = isset($parts['frequencies']) ? $parts['frequencies'] : [];
    foreach ($frequencies as $frequency => $frequencyParts) {
      $grid->allowParts($frequency, $frequencyParts);
    }

    return $grid;
  }

  public function isFrequencySupported($frequency) {
    assert(in_array($frequency, static::FREQUENCIES, TRUE));
    if ($this->isAllowEverything()) {
      return TRUE;
    }

    return isset($this->supportedParts[$frequency]) && count($this->supportedParts[$frequency]) > 0;
  }

  /**
   * Determines whether a part is supported.
   *
   * @param string $frequency
   *   A frequency.
   * @param string $part
   *   A part.
   *
   * @return bool
   *   Whether a part is supported.
   *
   * @throws \Drupal\date_recur\Exception\DateRecurRulePartIncompatible
   *   Part is incompatible with frequency.
   */
  public function isPartSupported($frequency, $part) {
    assert(in_array($frequency, static::FREQUENCIES, TRUE) && in_array($part, static::PARTS, TRUE));
    if (in_array($part, static::INCOMPATIBLE_PARTS[$frequency], TRUE)) {
      throw new DateRecurRulePartIncompatible();
    }

    if ($this->isAllowEverything()) {
      return TRUE;
    }

    $partsInFrequency = isset($this->supportedParts[$frequency]) ? $this->supportedParts[$frequency] : [];
    // Supports the part, or everything in this frequency.
    return in_array($part, $partsInFrequency, TRUE) || in_array('*', $partsInFrequency, TRUE);
  }

  public function isAllowEverything() {
    return count($this->supportedParts) === 0;
  }

  public function allowParts($frequency, array $parts) {
    $existingFrequencyParts = isset($this->supportedParts[$frequency]) ? $this->supportedParts[$frequency] : [];
    $this->supportedParts[$frequency] = array_merge($parts, $existingFrequencyParts);
  }

}
