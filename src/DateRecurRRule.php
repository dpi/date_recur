<?php

namespace Drupal\date_recur;

/**
 * This class handles all RRule related calculations. It calls out to
 * DateRecurDefaultRRule for actual calculations, so that this can possibly
 * be made pluggable for other implementations.
 *
 * @todo:
 * - Load occurrences from database cache instead of recalculating for each
 *   view (or at least benchmark this for different rules).
 * - Properly document and add an interface.
 * - Properly set cache tags, either here or in the formatter.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Datetime\DateTimePlus;
use RRule\RRule;
use RRule\RfcParser;
use RRule\RSet;


class DateRecurRRule implements \Iterator {

  const RFC_DATE_FORMAT = 'Ymd\THis\Z';

  /**
   * @var \Drupal\date_recur\DateRecurDefaultRSet
   */
  protected $rrule;

  /**
   * @var \DateTime
   */
  protected $startDate;
  protected $startDateEnd;

  /**
   * @var string
   */
  protected $recurTime;

  /**
   * @var \DateInterval
   */
  protected $recurDiff;

  /**
   * @var string
   */
  protected $originalRuleString;

  /**
   * @var array
   */
  protected $parts;

  /**
   * @var array
   */
  protected $setParts;

  /**
   * @var array
   */
  protected $occurrences;

  /**
   * @param string $rrule
   *   The repeat rule.
   * @param \DateTime|DrupalDateTime $startDate
   *   The start date
   * @param \DateTime|DrupalDateTime|NULL $startDateEn
   *   Optionally, the initial event's end date.
   * @param $timezone
   *
   * @throws \InvalidArgumentException
   */
  public function __construct($rrule, $startDate, $startDateEnd = NULL) {
    $this->originalRuleString = $rrule;
    $this->startDate = $startDate;
    $this->recurTime = $this->startDate->format('H:i');
    if (empty($startDateEnd)) {
      $startDateEnd = clone $startDate;
    }
    $this->startDateEnd = $startDateEnd;
    $this->recurDiff = $this->startDate->diff($startDateEnd);

    $this::parseRrule($rrule, $startDate);

    $this->rrule = new DateRecurDefaultRSet();
    $this->rrule->addRRule(new DateRecurDefaultRRule($this->parts));
    if (!empty($this->setParts)) {
      foreach ($this->setParts as $type => $type_parts) {
        foreach ($type_parts as $part) {
          list(, $part) = explode(':', $part);
          switch ($type) {
            case 'RDATE':
              $this->rrule->addDate($part);
              break;
            case 'EXDATE':
              $this->rrule->addExDate($part);
              break;
            case 'EXRULE':
              $this->rrule->addExRule($part);
          }
        }
      }
    }
  }

  public function getParts() {
    return $this->parts;
  }

  /**
   * Parse an RFC rrule string and add a start date (DTSTART).
   *
   * @param string $rrule
   * @param \DateTime|DrupalDateTime $startDate
   * @throws \InvalidArgumentException
   * @return array An array of rrule parts.
   */
  public function parseRrule($rrule, $startDate, $check_only = FALSE) {
    // Correct formatting.
    if (strpos($rrule, "\n") === FALSE && strpos($rrule, 'RRULE:') !== 0) {
      $rrule = "RRULE:$rrule";
    }

    // Check for unsupported parts.
    $set_keys = ['RDATE', 'EXRULE', 'EXDATE'];
    $rules = $set_parts = [];
    foreach (explode("\n", $rrule) as $key => $part) {
      $els = explode(':', $part);
      if (in_array($els[0], $set_keys)) {
        $set_parts[$els[0]][] = $part;
      }
      else if ($els[0] == 'RRULE') {
        $rules[] = $part;
      }
      else if ($els[0] == 'DTSTART') {
        $dtstart = $part;
      }
      else {
        throw new \InvalidArgumentException("Unsupported line: " . $part);
      }
    }

    if (!count($rules)) {
      throw new \InvalidArgumentException("Missing RRULE line: " . $rrule);
    }
    if (count($rules) > 1) {
      throw new \InvalidArgumentException("More than one RRULE line is not supported.");
    }

    $rrule = $rules[0];

    if (empty($parts['WKST'])) {
      $parts['WKST'] = 'MO';
    }
    $this->parts = RfcParser::parseRRule($rrule, $startDate);
    $this->setParts = $set_parts;
  }

  /**
  /**
   * Validate that an rrule string is parseable.
   *
   * @param string $rrule
   * @param \DateTime|DrupalDateTime $startDate
   * @throws \InvalidArgumentException
   * @return bool
   */
  public static function validateRule($rrule, $startDate) {
    $rule = new self($rrule, $startDate);
    return TRUE;
  }


  /**
   * Get occurrences, optionally limited by a start date, end date and count.
   *
   * @param null|\DateTime $start
   * @param null|\DateTime $end
   * @param null|int $num
   * @return array
   */
  public function getOccurrences($start = NULL, $end = NULL, $num = NULL) {
    return $this->createOccurrences($start, $end, $num);
  }

  /**
   * Get occurrences between a start and an end date.
   *
   * @param \DateTime|DrupalDateTime $start
   * @param \DateTime|DrupalDateTime $end
   * @return array
   */
  public function getOccurrencesBetween($start, $end) {
    return $this->getOccurrences($start, $end);
  }

  /**
   * Get the next occurrences after a start date.
   *
   * @param \DateTime|DrupalDateTime $start
   * @param int $num
   * @return array
   */
  public function getNextOccurrences($start, $num) {
    return $this->getOccurrences($start, NULL, $num);
  }

  /**
   * Check if the rule is infinite.
   *
   * @return bool
   */
  public function isInfinite() {
    return $this->rrule->isInfinite();
  }

  /**
   * Get the occurrences for storage in the cache table (for views).
   *
   * @see DateRecurFieldItemList::postSave()
   *
   * @param \DateTime $until For infinite dates create until that date.
   * @param string $storageFormat The desired date format.
   * @return array
   */
  public function getOccurrencesForCacheStorage(\DateTime $until, $storageFormat) {
    $occurrences = [];
    if (!$this->rrule->isInfinite()) {
      $occurrences += $this->createOccurrences(NULL, NULL, NULL, FALSE);
    }
    else {
      $occurrences += $this->createOccurrences(NULL, $until, NULL, FALSE);
    }

    foreach ($occurrences as &$row) {
      foreach ($row as $key => $date) {
        if (!empty($date)) {
          $row[$key] = self::massageDateValueForStorage($date, $storageFormat);
        }
      }
    }

    return $occurrences;
  }

  /**
   * Get occurrences between a range of dates.
   *
   * @param \DateTime|NULL $start
   *   The start of the range.
   * @param \DateTime|NULL $end
   *   The end of the range.
   * @param int|NULL $num
   *   Maximum number of occurrences.
   *
   * @return array
   *   An array containing arrays of:
   *    - value: the start date as DrupalDateTime.
   *    - end_value: the end date as DrupalDateTime.
   */
  protected function createOccurrences(\DateTime $start = NULL, \DateTime $end = NULL, $num = NULL) {
    if ($this->rrule->isInfinite() && !$end && !$num) {
      throw new \LogicException('Cannot get all occurrences of an infinite recurrence rule.');
    }

    $occurrences = [];
    foreach ($this->rrule as $occurrence) {
      /** @var \DateTime $occurrence */
      if ($start && $occurrence < $start) {
        continue;
      }
      if ($end && $occurrence > $end) {
        break;
      }
      if ($num && count($occurrences)  >= $num) {
        break;
      }
      $occurrences[] = $this->massageOccurrence($occurrence);
    }

    return $occurrences;
  }

  /**
   * Create a start and end range from an occurrence.
   *
   * @param \DateTime $occurrence
   *   An occurrence start time.
   *
   * @return array
   *   An array containing:
   *    - value: the start date as DrupalDateTime.
   *    - end_value: the end date as DrupalDateTime.
   */
  protected function massageOccurrence(\DateTime $occurrence) {
    $date = DrupalDateTime::createFromDateTime($occurrence);
    $date_end = (clone $date)->add($this->recurDiff);
    return ['value' => $date, 'end_value' => $date_end];
  }

  /**
   * @param $date
   * @return \DateTime $date
   */
  public function adjustDateForDisplay($date) {
    if (empty($this->timezone)) {
      return $date;
    }
    return $date->setTimezone(new \DateTimeZone($this->timezone));
  }

  public static function massageDateValueForStorage($date, $format) {
    if ($format == DATETIME_DATE_STORAGE_FORMAT) {
      datetime_date_default_time($date);
    }
    $date->setTimezone(new \DateTimeZone(DATETIME_STORAGE_TIMEZONE));
    // Adjust the date for storage.
    return $date->format($format);
  }

  public function getWeekdays() {

    $weekdays = [];
    $byday = $this->parts['BYDAY'];
    if (!is_array($byday) ) {
      $byday = explode(',', $byday);
    }

    $this->byweekday = array();
    $this->byweekday_nth = array();
    foreach ($byday as $value) {
      $value = trim(strtoupper($value));
      $valid = preg_match('/^([+-]?[0-9]+)?([A-Z]{2})$/', $value, $matches);

      if (!empty($matches[2])) {
        $day = RRule::$week_days[$matches[2]];
        $weekdays[$day] = $day;
      }
    }
    return $weekdays;

    if (!empty($this->parts['BYDAY'])) {
      $days = explode(',', $this->parts['BYDAY']);
      foreach ($days as $day) {
        $day = preg_replace('/[\+\-0-9]*/', '', $day);
        $weekdays[$day] = $day;
      }
    }
    return $weekdays;
  }

  /**
   * Get a human-readable representation of the repeat rule.
   *
   * @todo: Make this translatable.
   *
   * @return string
   */
  public function humanReadable() {
    return $this->rrule->humanReadable();
  }

  /**
   * Return the current element
   */
  public function current() {
    if ($date = $this->rrule->current()) {
      return $this->massageOccurrence($date);
    }
  }

  /**
   * Move forward to next element
   */
  public function next() {
    if ($date = $this->rrule->next()) {
      return $this->massageOccurrence($date);
    }
  }

  /**
   * Return the key of the current element
   */
  public function key() {
    return $this->rrule->key();
  }

  /**
   * Checks if current position is valid
   */
  public function valid() {
    return $this->rrule->valid();
  }

  /**
   * Rewind the Iterator to the first element
   */
  public function rewind() {
    $this->rrule->rewind();
  }
}
