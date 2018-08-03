<?php

namespace Drupal\date_recur;

/**
 * Defines an interface for methods provided by RRule but not on its interface.
 *
 * Some methods on \RRule\RRule are not defined in its interface at
 * \RRule\RRuleInterface.
 */
interface RRuleMissingInterface {

  public function humanReadable();

}