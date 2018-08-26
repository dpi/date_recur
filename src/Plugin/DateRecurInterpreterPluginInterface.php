<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;

/**
 * Xd.
 */
interface DateRecurInterpreterPluginInterface extends ConfigurablePluginInterface, PluginWithFormsInterface {

  /**
   * Interpret.
   *
   * @param \Drupal\date_recur\DateRecurRuleInterface[] $rules
   *   X.
   *
   * @return string
   *   y.
   *
   * @todo change param from rules to recurrence objects.
   */
  public function interpret(array $rules);

}
