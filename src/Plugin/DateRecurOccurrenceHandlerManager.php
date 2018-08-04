<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Date recur occurrence handler plugin manager.
 *
 * A new instance of a occurrence handler is created on demand for each field
 * item.
 */
class DateRecurOccurrenceHandlerManager extends DefaultPluginManager implements DateRecurOccurrenceHandlerManagerInterface {

  /**
   * Creates a new DateRecurOccurrenceHandlerManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/DateRecurOccurrenceHandler', $namespaces, $module_handler, 'Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface', 'Drupal\date_recur\Annotation\DateRecurOccurrenceHandler');
    $this->alterInfo('date_recur_date_recur_occurrence_handler_info');
    $this->setCacheBackend($cache_backend, 'date_recur_date_recur_occurrence_handler_plugins');
  }

}
