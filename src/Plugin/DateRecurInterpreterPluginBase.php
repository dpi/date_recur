<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;

/**
 * Base class for date recur interpreter plugins.
 */
abstract class DateRecurInterpreterPluginBase extends PluginBase implements DateRecurInterpreterPluginInterface {

  use PluginWithFormsTrait;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

}
