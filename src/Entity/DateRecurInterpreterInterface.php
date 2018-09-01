<?php

namespace Drupal\date_recur\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Interface for Recurring Date interpreters.
 */
interface DateRecurInterpreterInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Get the plugin.
   *
   * @return \Drupal\date_recur\Plugin\DateRecurInterpreterPluginInterface
   *   The plugin.
   */
  public function getPlugin();

  /**
   * Set the plugin ID.
   *
   * @param string $plugin_id
   *   The plugin ID.
   */
  public function setPlugin($plugin_id);

}
