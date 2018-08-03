<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManagerInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;

/**
 * Plugin implementation of the 'date_recur' field type.
 *
 * @FieldType(
 *   id = "date_recur",
 *   label = @Translation("Date Recur"),
 *   description = @Translation("Recurring dates field"),
 *   default_widget = "date_recur_default_widget",
 *   default_formatter = "date_recur_default_formatter",
 *   list_class = "\Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList"
 * )
 */
class DateRecurItem extends DateRangeItem {

  /**
   * The initialized occurrence manager.
   *
   * @var \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface|null
   */
  protected $occurrenceHandler = NULL;

  /**
   * The occurrence handler manager.
   *
   * Used for unit testing, not set during normal operation..
   *
   * @var \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManagerInterface|null
   */
  protected $occurrenceHandlerPluginManager = NULL;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['rrule'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('RRule'))
      ->setRequired(FALSE);
    $properties['timezone'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Timezone'))
      ->setRequired(FALSE);
    $properties['infinite'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Whether the RRule is an infinite rule. Derived value from RRULE.'))
      ->setRequired(FALSE);

    // @todo inject or something.
    $properties['occurrences'] = date_recur_create_occurrence_handler($field_definition)
      ->occurrencePropertyDefinition($field_definition);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['rrule'] = [
      'description' => 'The repeat rule.',
      'type' => 'varchar',
      'length' => 255,
    ];
    $schema['columns']['timezone'] = [
      'description' => 'The timezone.',
      'type' => 'varchar',
      'length' => 255,
    ];
    $schema['columns']['infinite'] = [
      'description' => 'Whether the RRule is an infinite rule. Derived value from RRULE.',
      'type' => 'int',
      'size' => 'tiny',
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'occurrence_handler_plugin' => 'date_recur_occurrence_handler',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = parent::storageSettingsForm($form, $form_state, $has_data);

    $options = array_map(function (array $definition) {
      return $definition['label'];
    }, $this->getOccurrenceHandlerPluginManager()->getDefinitions());

    $elements['occurrence_handler_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Occurrence handler'),
      '#description' => $this->t('Select an occurrence handler for calculating, saving, and retrieving occurrences.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('occurrence_handler_plugin'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      // @todo needs settings tests.
      'precreate' => 'P2Y',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    // @todo Needs UI tests.
    $options = [];
    foreach (range(1, 5) as $i) {
      $options['P' . $i . 'Y'] = $this->formatPlural($i, '@year year', '@year years', ['@year' => $i]);
    }
    // @todo allow custom values.
    $element['precreate'] = [
      '#type' => 'select',
      '#title' => $this->t('Precreate occurrences'),
      '#description' => $this->t('For infinitely repeating dates, precreate occurrences for this amount of time in the views cache table.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('precreate'),
    ];

    return $element;
  }

  /**
   * Get the date storage format of this field.
   *
   * @return string
   *   A date format string.
   */
  public function getDateStorageFormat() {
    // @todo tests.
    return $this->getSetting('daterange_type') == static::DATETIME_TYPE_DATETIME ? static::DATETIME_STORAGE_FORMAT : static::DATE_STORAGE_FORMAT;
  }

  /**
   * Get the occurrence handler and initialize it.
   *
   * @return \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface|\Drupal\date_recur\Plugin\DateRecurOccurrenceHandler\DefaultDateRecurOccurrenceHandler
   *   An occurrence handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Exception thrown if plugin could not be created.
   */
  public function getOccurrenceHandler() {
    // @todo rename, its long.
    if (!isset($this->occurrenceHandler)) {
      $this->occurrenceHandler = $this->getPlugin();
      $this->occurrenceHandler->init($this);
    }
    return $this->occurrenceHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Enforce that the occurrence handler is re-initialized.
    // @todo test occurrencehandler is reinitialised.
    $this->occurrenceHandler = NULL;
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    // @todo test infinite prop is set.
    $isInfinite = $this->getOccurrenceHandler()->isInfinite();
    $this->get('infinite')->setValue($isInfinite);
  }

  /**
   * Get the item's delta.
   *
   * Field items have their name property set to the delta.
   *
   * @return int
   *   The delta.
   *
   * @deprecated should not be required. List already have deltas.
   * @internal used by views only.
   */
  public function getDelta() {
    return (int) $this->name;
  }

  /**
   * Get the occurrence handler manager.
   *
   * @return \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManagerInterface
   *   The occurrence handler plugin manager.
   */
  protected function getOccurrenceHandlerPluginManager() {
    if (isset($this->occurrenceHandlerPluginManager)) {
      return $this->occurrenceHandlerPluginManager;
    }
    return \Drupal::service('plugin.manager.date_recur_occurrence_handler');
  }

  /**
   * Set the occurrence handler manager.
   *
   * Used for unit testing.
   *
   * @param \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManagerInterface $occurrenceHandlerManager
   *   The occurrence handler plugin manager.
   */
  public function setOccurrenceHandlerPluginManager(DateRecurOccurrenceHandlerManagerInterface $occurrenceHandlerManager) {
    $this->occurrenceHandlerPluginManager = $occurrenceHandlerManager;
  }

  /**
   * Todo.
   *
   * @return \Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface
   *   Todo.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getPlugin() {
    $pluginName = $this->getSetting('occurrence_handler_plugin');
    return $this->getOccurrenceHandlerPluginManager()
      ->createInstance($pluginName);
  }

}
