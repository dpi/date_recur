<?php

namespace Drupal\date_recur\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Date recur occurrence handler item annotation object.
 *
 * @see \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManager
 * @see plugin_api
 *
 * @Annotation
 */
class DateRecurInterpreter extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
