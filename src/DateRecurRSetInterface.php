<?php

namespace Drupal\date_recur;


use RRule\RRuleInterface;

interface DateRecurRSetInterface extends RRuleInterface {


  public function humanReadable();

}
