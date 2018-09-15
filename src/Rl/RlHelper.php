<?php

namespace Drupal\date_recur\Rl;

use Drupal\date_recur\DateRange;
use Drupal\date_recur\DateRecurHelperInterface;

/**
 * Helper for recurring rules implemented with rlanvin/rrule.
 *
 * @ingroup RLanvinPhpRrule
 */
class RlHelper implements DateRecurHelperInterface {

  /**
   * The RRULE set.
   *
   * @var \Drupal\date_recur\Rl\RlRSet
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
   * Constructor for DateRecurHelper.
   *
   * @param string $string
   *   The repeat rule.
   * @param \DateTimeInterface $dtStart
   *   The initial occurrence start date.
   * @param \DateTimeInterface|null $dtStartEnd
   *   The initial occurrence end date, or NULL to use start date.
   */
  public function __construct($string, \DateTimeInterface $dtStart, \DateTimeInterface $dtStartEnd = NULL) {
    $dtStartEnd = isset($dtStartEnd) ? $dtStartEnd : clone $dtStart;
    $this->recurDiff = $dtStart->diff($dtStartEnd);

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

    $this->set = new RlRSet();

    foreach ($parts as $type => $values) {
      foreach ($values as $value) {
        switch ($type) {
          case 'RRULE':
            $this->set->addRRule(new RlRRule($value, $dtStart));
            break;

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
   * {@inheritdoc}
   */
  public static function createInstance($string, \DateTimeInterface $dtStart, \DateTimeInterface $dtStartEnd = NULL) {
    return new static($string, $dtStart, $dtStartEnd);
  }

  /**
   * {@inheritdoc}
   */
  public function getRules() {
    return array_map(
      function (RlRRule $rule) {
        return new RlDateRecurRule($rule->getRule());
      },
      $this->set->getRRules()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isInfinite() {
    return $this->set->isInfinite();
  }

  /**
   * {@inheritdoc}
   */
  public function generateOccurrences(\DateTimeInterface $rangeStart = NULL, \DateTimeInterface $rangeEnd = NULL) {
    foreach ($this->set as $occurrenceStart) {
      /** @var \DateTime $occurrence */
      $occurrenceEnd = clone $occurrenceStart;
      $occurrenceEnd->add($this->recurDiff);

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
  }

  /**
   * {@inheritdoc}
   */
  public function getOccurrences(\DateTimeInterface $rangeStart = NULL, \DateTimeInterface $rangeEnd = NULL, $limit = NULL) {
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
   * {@inheritdoc}
   */
  public function current() {
    $occurrenceStart = $this->set->current();
    $occurrenceEnd = clone $occurrenceStart;
    $occurrenceEnd->add($this->recurDiff);
    return new DateRange($occurrenceStart, $occurrenceEnd);
  }

  /**
   * {@inheritdoc}
   */
  public function next() {
    $this->set->next();
  }

  /**
   * {@inheritdoc}
   */
  public function key() {
    return $this->set->key();
  }

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return $this->set->valid();
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    $this->set->rewind();
  }

  /**
   * Get the set.
   *
   * @return \Drupal\date_recur\Rl\RlRSet
   *   Returns the set.
   *
   * @internal this method is specific to rlanvin/rrule implementation only.
   */
  public function getRlRuleset() {
    return $this->set;
  }

}
