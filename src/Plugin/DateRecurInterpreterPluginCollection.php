<?php

namespace Drupal\date_recur\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a container for lazily loading Date Recur Interpreter plugins.
 */
class DateRecurInterpreterPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * Constructs a new SmsGatewayPluginCollection object.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The manager to be used for instantiating plugins.
   * @param string $instance_id
   *   The ID of the plugin instance.
   * @param array $configuration
   *   An array of configuration.
   */
  public function __construct(PluginManagerInterface $manager, $instance_id, array $configuration, $id) {
    parent::__construct($manager, $instance_id, $configuration);
    $this->id = $id;
  }

}
