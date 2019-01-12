<?php

// @codingStandardsIgnoreFile

namespace Drupal\date_recur\Rl;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use RRule\RRule;
use RRule\RRuleInterface;

/**
 * Wrapper around rlanvin/RRule.
 *
 * @ingroup RLanvinPhpRrule
 */
class RlRRule extends RRule implements RRuleInterface {

  use StringTranslationTrait;

  protected $timezoneOffset;

  public function getStartDate() {
    if (!empty($this->dtstart)) {
      return $this->dtstart;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Set a timezone offset to add to all dates for display.
   *
   * @param int $offset Timezone offset in seconds.
   */
  public function setTimezoneOffset($offset) {
    $this->timezoneOffset = $offset;
  }

  /**
   * Return a human readable and translated representation of the repeat rule.
   *
   * This is the start for a Drupal-translated and improved converter. So far
   * only handles a few cases. For unhandled cases, this calls back to
   * RRule\RRule::humanReadable.
   *
   * @todo: Cover more (all) cases.
   *
   * @param array $opt
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   */
  public function humanReadable(array $opt = []) {
    $build = [];

    $daynames = [
      new TranslatableMarkup('Sunday'),
      new TranslatableMarkup('Monday'),
      new TranslatableMarkup('Tuesday'),
      new TranslatableMarkup('Wednesday'),
      new TranslatableMarkup('Thursday'),
      new TranslatableMarkup('Friday'),
      new TranslatableMarkup('Saturday'),
    ];

    array_push($daynames, $daynames[0]);
    unset($daynames[0]);
    $monthnames = [
      1  => new TranslatableMarkup('January'),
      2  => new TranslatableMarkup('February'),
      3  => new TranslatableMarkup('March'),
      4  => new TranslatableMarkup('April'),
      5  => new TranslatableMarkup('May'),
      6  => new TranslatableMarkup('June'),
      7  => new TranslatableMarkup('July'),
      8  => new TranslatableMarkup('August'),
      9  => new TranslatableMarkup('September'),
      10 => new TranslatableMarkup('October'),
      11 => new TranslatableMarkup('November'),
      12 => new TranslatableMarkup('December'),
    ];

    $parts = [];

    if (!empty($this->byweekday)) {
      $dayparts = [];
      foreach ($this->byweekday as $day) {
        $dayparts[] = $daynames[$day];
      }
      $parts['day'] = $this->_hrFormatList($dayparts);
    }
    elseif (!empty($this->byweekday_nth)) {
      $dayparts = $days = [];
      foreach ($this->byweekday_nth as $info) {
        $days[$info[0]][] = $info[1];
      }
      foreach ($days as $day => $pos) {
        $dayparts[] = $this->getString('pos_day', [
          '@pos' => $this->_hrFormatPosList($pos),
          '@day' => $daynames[$day],
        ]);
      }
      $parts['day'] = $this->_hrFormatList($dayparts);
    }
    switch ($this->freq) {
      case self::WEEKLY:
        $build['rule'] = $this->getString('weekly', ['@day' => $parts['day']]);
        break;
      case self::MONTHLY:
        $build['rule'] = $this->getString('monthly', ['@posday' => $parts['day']]);
        break;
    }

    if (!empty($this->dtstart)) {
      $build['time'] = $this->formatDateForDisplay($this->dtstart, 'H:i');
    }


    if (!empty($build['rule'])) {
      return $this->getString('complete', ['@rule' => $build['rule'], '@time' => $build['time']]);
    }
    else {
      $string = parent::humanReadable($opt);
      return $string;
    }

  }

  protected function getString($string, $args) {
    /** @var TranslatableMarkup[] $strings */
    $strings = [
      'complete' => new TranslatableMarkup('@rule at @time'),
      'and' => new TranslatableMarkup('@a and @b'),
      'daily' => new TranslatableMarkup('Every day'),
      'weekly' => new TranslatableMarkup('Every week on @day'),
      'monthly' => new TranslatableMarkup('On the @posday each month'),
      'pos_day' => new TranslatableMarkup('@pos @day'),
      'last_1' => new TranslatableMarkup('last'),
      'last_2' => new TranslatableMarkup('second to last'),
    ];

    $string = !empty($strings[$string]) ? $strings[$string]->getUntranslatedString() : '';

    return $this->t($string, $args);
  }

  protected function _hrFormatPosList($list) {
    // Sort like this: 1, 2, 3, 4, 5, -1, -2, -3.
    usort($list, function($a, $b) {
      if ($a >= 0 && $b >= 0) {
        return $a > $b;
      }
      else if ($a >= 0 && $b < 0) {
        return -1;
      }
      else {
        return 1;
      }
     });
    // Append a dot for positive, get a string for ultimates.
    $list = array_map(function($i) {
      if ($i > 0) {
        return $i . '.';
      }
      elseif ($i > -3) {
        return $this->getString('last_' . abs($i));
      }
      else {
        return $i;
      }
    }, $list);
    // Format as list.
    return $this->_hrFormatList($list);
  }

  /**
   * Format a variable-length list into a sentence style string.
   *
   * Like this, for a list with 4 items: A, B, C and D
   * Or with 2 items: A and B
   *
   * @param string[] $list
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected function _hrFormatList($list) {
    if (is_string($list)) {
      return $list;
    }
    if (count($list) == 1) {
      return $list[0];
    }
    else {
      $args['@b'] = array_pop($list);
      $args['@a'] = implode(', ', $list);
      return $this->getString('and', $args);
    }
  }

  protected function formatDateForDisplay(\DateTime $date, $format = 'H:i') {
    if (empty($this->timezone)) {
      return $date->format($format);
    }
    return $date->setTimezone(new \DateTimeZone($this->timezone))->format($format);
  }
}
