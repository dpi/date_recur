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
use RRule\RRule;
use RRule\RfcParser;

/**
 * Defines the DateRecurRRule class.
 */
class DateRecurRRule implements \Iterator {

  /**
   * The RRULE object.
   *
   * @var \Drupal\date_recur\DateRecurDefaultRSet
   */
  protected $rrule;

  /**
   * The initial occurrence start date.
   *
   * @var \DateTime
   */
  protected $startDate;

  /**
   * The initial occurrence end date.
   *
   * @var \DateTime
   */
  protected $startDateEnd;

  /**
   * Difference between start date and start date end.
   *
   * Calculated value.
   *
   * @var \DateInterval
   */
  protected $recurDiff;

  /**
   * Parts.
   *
   * @var array
   */
  protected $parts;

  /**
   * Set parts.
   *
   * @var array
   */
  protected $setParts;

  /**
   * Construct a new DateRecurRRule.
   *
   * @param string $rrule
   *   The repeat rule.
   * @param \DateTime $startDate
   *   The initial occurrence start date.
   * @param \DateTime|null $startDateEnd
   *   The initial occurrence end date.
   */
  public function __construct($rrule, \DateTime $startDate, \DateTime $startDateEnd) {
    $this->startDate = $startDate;
    $this->startDateEnd = $startDateEnd;
    $this->recurDiff = $this->startDate->diff($startDateEnd);

    $this->parseRrule($rrule, $startDate);

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
   *   The repeat rule.
   * @param \DateTime $startDate
   *   The initial occurrence start date.
   *
   * @throws \InvalidArgumentException
   *   If the RRULE is malformed.
   */
  public function parseRrule($rrule, \DateTime $startDate) {
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
      elseif ($els[0] == 'RRULE') {
        $rules[] = $part;
      }
      elseif ($els[0] == 'DTSTART') {
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
   * @param \DateTime|null $start
   *   The start of the range.
   * @param \DateTime|null $end
   *   The end of the range.
   * @param int|null $num
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
      // Allow the occurrence if it is partially within the duration of the
      // range.
      $dateEnd = (clone $occurrence)->add($this->recurDiff);

      if ($start) {
        if ($occurrence < $start && $dateEnd < $start) {
          continue;
        }
      }

      if ($end) {
        if ($occurrence > $end && $dateEnd > $end) {
          break;
        }
      }

      if ($num && count($occurrences) >= $num) {
        break;
      }
      $occurrences[] = $this->massageOccurrence($occurrence);
    }

    return $occurrences;
  }

  /**
   * Calculates occurrences as a generator.
   *
   * @param \DateTime|null $rangeStart
   *   The start of the range, or start with the first available occurrence.
   * @param \DateTime|null $rangeEnd
   *   The end of the range, or never end.
   *
   * @return \Generator|\Drupal\date_recur\DateRange[]
   *   A generator.
   */
  public function generateOccurrences(\Datetime $rangeStart = NULL, \Datetime $rangeEnd = NULL) {
    foreach ($this->rrule as $occurrenceStart) {
      /** @var \DateTime $occurrence */
      $occurrenceEnd = (clone $occurrenceStart)->add($this->recurDiff);

      if ($rangeStart) {
        if ($occurrenceStart < $rangeStart && $occurrenceEnd < $rangeStart) {
          continue;
        }
      }

      if ($rangeEnd) {
        if ($occurrenceStart > $rangeEnd && $occurrenceEnd > $rangeEnd) {
          break;
        }
      }

      yield new DateRange($occurrenceStart, $occurrenceEnd);
    }

    return NULL;
  }

  /**
   * Get all occurrences.
   *
   * A limit and/or range-end must be passed.
   *
   * @param \DateTime|null $rangeStart
   *   The start of the range, or start with the first available occurrence.
   * @param \DateTime|null $rangeEnd
   *   The end of the range.
   * @param int|null $limit
   *   A limit.
   *
   * @return \Drupal\date_recur\DateRange[]
   *   The occurrences.
   *
   * @throws \InvalidArgumentException
   *   Exceptions thrown if ranges are invalid or undefined.
   */
  public function getAllOccurrences(\Datetime $rangeStart = NULL, \Datetime $rangeEnd = NULL, $limit = NULL) {
    // @todo rename to getOccurrences.
    if ($this->isInfinite() && !isset($rangeEnd) && !isset($limit)) {
      throw new \InvalidArgumentException('An infinite rule must have a date or count limit.');
    }

    $generator = $this->generateOccurrences($rangeStart, $rangeEnd);
    if (isset($limit)) {
      if (!is_int($limit) || $limit <= 0) {
        // Limit must be a number and more than one.
        throw new \InvalidArgumentException('Invalid count limit.');
      }

      // Generate occurrences until the limit is reached.
      $occurrences = [];
      foreach ($generator as $value) {
        $occurrences[] = $value;
        if (count($occurrences) >= $limit) {
          break;
        }
      }
      return $occurrences;
    }

    return iterator_to_array($generator);
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
   *
   * @deprecated Will be removed before beta.
   * @internal
   */
  protected function massageOccurrence(\DateTime $occurrence) {
    $date = DrupalDateTime::createFromDateTime($occurrence);
    $dateEnd = (clone $date)->add($this->recurDiff);
    return [
      'value' => $date,
      'end_value' => $dateEnd,
    ];
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
