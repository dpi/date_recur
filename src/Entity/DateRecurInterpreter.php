<?php

namespace Drupal\date_recur\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\date_recur\Plugin\DateRecurInterpreterPluginCollection;

/**
 * Defines an instance of Recurring Date interpreter.
 *
 * @ConfigEntityType(
 *   id = "date_recur_interpreter",
 *   label = @Translation("Recurring date interpreter"),
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\date_recur\Entity\Handlers\DateRecurOccurrenceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\date_recur\Form\DateRecurInterpreterAddForm",
 *       "delete" = "Drupal\date_recur\Form\DateRecurInterpreterDeleteForm",
 *       "edit" = "Drupal\date_recur\Form\DateRecurInterpreterEditForm",
 *     },
 *   },
 *   admin_permission = "date_recur manage interpreters",
 *   config_prefix = "interpreter",
 *   links = {
 *     "canonical" = "/admin/config/regional/recurring-date-interpreters/manage/{date_recur_interpreter}",
 *     "add-form" = "/admin/config/regional/recurring-date-interpreters/add",
 *     "edit-form" = "/admin/config/regional/recurring-date-interpreters/manage/{date_recur_interpreter}",
 *     "delete-form" = "/admin/config/regional/recurring-date-interpreters/manage/{date_recur_interpreter}/delete",
 *     "collection" = "/admin/config/regional/recurring-date-interpreters",
 *   },
 * )
 */
class DateRecurInterpreter extends ConfigEntityBase implements DateRecurInterpreterInterface {

  /**
   * The machine name.
   *
   * @var string
   */
  protected $id;

  /**
   * The custom label.
   *
   * @var string
   */
  protected $label;

  /**
   *
   *
   * @var string
   */
  protected $plugin;

  /**
   *
   *
   * @var array
   */
  protected $settings = [];

  /**
   *
   *
   * @var
   */
  protected $pluginCollection;

  /**
   *
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The action's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new DateRecurInterpreterPluginCollection(
        \Drupal::service('plugin.manager.date_recur_interpreter'),
        $this->plugin,
        $this->settings,
        $this->id
      );
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['settings' => $this->getPluginCollection()];
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

}
