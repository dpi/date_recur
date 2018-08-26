<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 *
 */
class DateRecurInterpreterManager extends DefaultPluginManager implements DateRecurInterpreterManagerInterface {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/DateRecurInterpreter', $namespaces, $module_handler, 'Drupal\date_recur\Plugin\DateRecurInterpreterPluginInterface', 'Drupal\date_recur\Annotation\DateRecurInterpreter');
//    $this->alterInfo('action_info');
//    $this->setCacheBackend($cache_backend, 'action_info');
  }

}
