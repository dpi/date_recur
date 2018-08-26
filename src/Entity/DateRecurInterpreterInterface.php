<?php

namespace Drupal\date_recur\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 *
 */
interface DateRecurInterpreterInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {


  /**
   * @return \Drupal\date_recur\Plugin\DateRecurInterpreterPluginInterface
   * 
   * @throws \Exception
   */
  public function getPlugin();
  
}
