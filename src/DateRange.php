<?php

namespace Drupal\date_recur;

/**
 * Defines a date range.
 */
class DateRange {

  /**
   * The start date.
   *
   * @var \DateTime
   */
  protected $start;

  /**
   * The end date.
   *
   * @var \DateTime
   */
  protected $end;

  /**
   * Creates a new DateRange.
   *
   * @param \DateTime $start
   *   The start date.
   * @param \DateTime $end
   *   The end date.
   */
  public function __construct(\DateTime $start, \DateTime $end) {
    $this->start = $start;
    $this->end = $end;
  }

  /**
   * Get the start date.
   *
   * @return \DateTime
   *   The start date.
   */
  public function getStart(): \DateTime {
    return $this->start;
  }

  /**
   * Get the end date.
   *
   * @return \DateTime
   *   The end date.
   */
  public function getEnd(): \DateTime {
    return $this->end;
  }

}
