<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface for date recur occurrence handler plugin manager.
 *
 * @method \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface createInstance($plugin_id, array $configuration = []);
 */
interface DateRecurOccurrenceHandlerManagerInterface extends PluginManagerInterface {

}
