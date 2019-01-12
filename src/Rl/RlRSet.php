<?php

// @codingStandardsIgnoreFile

namespace Drupal\date_recur\Rl;

use RRule\RRule;
use RRule\RRuleInterface;
use RRule\RSet;

/**
 * Wrapper around rlanvin/RSet.
 *
 * @ingroup RLanvinPhpRrule
 */
class RlRSet extends RSet implements RRuleInterface {

  /**
   * {@inheritdoc}
   */
  public function humanReadable() {
    $text = $this->rrules[0]->humanReadable();
    if (!empty($this->rdates)) {
      $dates = [];
      foreach ($this->rdates as $date) {
        $dates[] = $this->formatDateForDisplay($date);
      }
      $text .= ', ' . t('and also on: @dates', [
          '@dates' => implode(', ', $dates)
        ]);
    }
    if (!empty($this->exdates)) {
      $exdates = [];
      foreach ($this->exdates as $date) {
        $exdates[] = $this->formatDateForDisplay($date);
      }
      $text .= ', ' . t('but not on: @dates', [
          '@dates' => implode(', ', $exdates)
      ]);
    }
    return $text;
  }

  public function setTimezoneOffset($offset) {
    foreach ($this->rrules as $rule) {
      $rule->setTimezoneOffset($offset);
    }
  }

  /**
   * Get the timezone.
   *
   * @return \DateTimeZone
   *   The timezone of the start date.
   */
  public function getTimezone() {
    $rrule = reset($this->rrules);
    return $rrule->getStartDate()->getTimezone();
  }

  public function addDate($datestr) {
    foreach ($this->_fixDates($datestr) as $date) {
      parent::addDate($date);
    }
    return $this;
  }
  public function addExDate($datestr) {
    foreach ($this->_fixDates($datestr) as $date) {
      parent::addExDate($date);
    }
    return $this;
  }

  protected function _fixDates($datestr) {
    if (is_string($datestr) && strpos($datestr, ',')) {
      $dates = explode(',', $datestr);
    }
    else {
      $dates = [$datestr];
    }

    $dtstart = $this->getStartDate();

    foreach ($dates as $key => $datestr) {
      $date = RRule::parseDate($datestr);
      if ($dtstart) {
        $date->setTimezone($dtstart->getTimezone());
        $date->setTime($dtstart->format('H'), $dtstart->format('i'));
      }
      $dates[$key] = $date;
    }
    return $dates;
  }

  public function getStartDate() {
    if (!empty($this->rrules[0])) {
      return $this->rrules[0]->getStartDate();
    }
    return FALSE;
  }

  protected function formatDateForDisplay(\DateTime $date, $format = 'd.m.Y') {
    if (empty($this->getTimezone())) {
      return $date->format($format);
    }
    return $date->setTimezone(new \DateTimeZone($this->getTimezone()))->format($format);
  }

}
