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
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\date_recur\Rl\RlRSet;
use Drupal\date_recur\Rl\RlRRule;
use RRule\RRule;
use RRule\RfcParser;

/**
 * Defines the DateRecurRRule class.
 *
 * @internal
 * @deprecated Use DateRecurHelper instead.
 */
class LegacyDateRecurRRule implements \Iterator {

  /**
   * BY* list delimiter.
   *
   * @internal Will be made protected when PHP7.1 is required.
   */
  const BY_LIST_DELIMITER = ',';

  /**
   * The RRULE set.
   *
   * @var \Drupal\date_recur\DateRecurRSetInterface
   */
  protected $set;

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
   *   The initial occurrence end date, or NULL to use start date.
   */
  public function __construct($rrule, \DateTime $startDate, \DateTime $startDateEnd = NULL) {
    $startDateEnd = isset($startDateEnd) ? $startDateEnd : clone $startDate;
    $this->recurDiff = $startDate->diff($startDateEnd);

    list($parts, $setParts) = static::parseRrule($rrule, $startDate);
    $this->parts = $parts; // @todo remove: backcompat.

    $this->set = (new RlRSet())
      ->addRRule(new RlRRule($parts));

    foreach ($setParts as $type => $values) {
      foreach ($values as $value) {
        switch ($type) {
          case 'RDATE':
            $this->set->addDate($value);
            break;

          case 'EXDATE':
            $this->set->addExDate($value);
            break;

          case 'EXRULE':
            $this->set->addExRule($value);
        }
      }
    }
  }

  /**
   * Get the RULE parts.
   *
   * For example, "FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;" will return:
   *
   * @code
   *
   * [
   *   'BYDAY' => 'MO,TU,WE,TH,FR',
   *   'DTSTART' => \DateTime(...),
   *   'FREQ' => 'WEEKLY',
   * ]
   *
   * @endcode
   *
   * @return array
   *   The parts of the RRULE.
   */
  public function getParts() {
    return $this->parts;
  }

  /**
   * Parse RRULE lines.
   *
   * @param string $string
   *   The rule lines.
   * @param \DateTime $startDate
   *   The initial occurrence start date.
   *
   * @return array
   *   A tuple containing:
   *   - RRULE parts.
   *   - Other set parts.
   *
   * @throws \InvalidArgumentException
   *   If the RRULE is malformed.
   */
  public static function parseRrule($string, \DateTime $startDate) {
    // @todo move elsewhere, make static.
    // Ensure the string is prefixed with RRULE if not multiline.
    if (strpos($string, "\n") === FALSE && strpos($string, 'RRULE:') !== 0) {
      $string = "RRULE:$string";
    }

    $parts = [
      'RDATE' => [],
      'EXRULE' => [],
      'EXDATE' => [],
      'RRULE' => [],
    ];

    $lines = explode("\n", $string);
    foreach ($lines as $line) {
      list($part, $partValue) = explode(':', $line, 2);
      if (!isset($parts[$part])) {
        throw new \InvalidArgumentException("Unsupported line: " . $part);
      }
      $parts[$part][] = $partValue;
    }

    if (($count = count($parts['RRULE'])) !== 1) {
      throw new \InvalidArgumentException(sprintf('One RRULE must be provided. %d provided.', $count));
    }

    $rruleParts = RfcParser::parseRRule(reset($parts['RRULE']), $startDate);
    unset($parts['RRULE']);
    return [$rruleParts, $parts];
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
    // test.
    // validte should be bool no exception. implies safety. if u watn exceptions then you'd call this manually.
//    If you want the message then...
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
    if ($this->set->isInfinite() && !$end && !$num) {
      throw new \LogicException('Cannot get all occurrences of an infinite recurrence rule.');
    }

    $occurrences = [];
    foreach ($this->set as $occurrence) {
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

  public static function massageDateValueForStorage(\DateTime $date, $format) {
    if ($format == DateRecurItem::DATE_STORAGE_FORMAT) {
      $date->setTime(12, 0, 0);
    }
    $date->setTimezone(new \DateTimeZone(DateRecurItem::STORAGE_TIMEZONE));
    // Adjust the date for storage.
    return $date->format($format);
  }

  /**
   * Get the BYDAY part as an array.
   *
   * @return array
   *   An array of weekday code keyed by weekday number, see
   *   \RRule\RRule::$week_days for a map. Array is empty if RRULE did not
   *   contain any weekdays.
   *
   * @see \RRule\RRule::$week_days
   */
  public function getWeekdays() {
    if (!isset($this->parts['BYDAY'])) {
      return [];
    }
    $byDay = explode(static::BY_LIST_DELIMITER, $this->parts['BYDAY']);
    // The following will sort the days and remove duplicates.
    return array_intersect(array_flip(RRule::$week_days), $byDay);
  }

  /**
   * Return the current element.
   */
  public function current() {
    if ($date = $this->set->current()) {
      return $this->massageOccurrence($date);
    }
  }

  /**
   * Move forward to next element.
   */
  public function next() {
    if ($date = $this->set->next()) {
      return $this->massageOccurrence($date);
    }
  }

  /**
   * Return the key of the current element.
   */
  public function key() {
    return $this->set->key();
  }

  /**
   * Checks if current position is valid.
   */
  public function valid() {
    return $this->set->valid();
  }

  /**
   * Rewind the Iterator to the first element.
   */
  public function rewind() {
    $this->set->rewind();
  }

  /**
   * Implements the magic __call method.
   *
   * Passes through all unknown calls onto the RSET object.
   *
   * @param string $method
   *   The method to call on the decorated object.
   * @param array $args
   *   Call arguments.
   *
   * @return mixed
   *   The return value from the method on the decorated object.
   */
  public function __call($method, array $args) {
    if (!method_exists($this->set, $method)) {
      throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', get_class($this), $method));
    }
    return call_user_func_array([$this->set, $method], $args);
  }

}
