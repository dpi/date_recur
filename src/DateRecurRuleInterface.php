<?php

namespace Drupal\date_recur;

/**
 * Defines an interface for a single rule.
 *
 * Normalizes rule class implementations.
 */
interface DateRecurRuleInterface {

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
  public function getParts();

}
