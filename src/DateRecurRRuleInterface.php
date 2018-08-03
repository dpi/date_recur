<?php

namespace Drupal\date_recur;


use RRule\RRuleInterface;

interface DateRecurRRuleInterface extends RRuleInterface, RRuleMissingInterface {

  public function getStartDate();

}
