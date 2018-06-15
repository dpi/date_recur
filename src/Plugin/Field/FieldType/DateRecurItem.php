<?php

namespace Drupal\date_recur\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerManager;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\date_recur\Plugin\DateRecurOccurrenceHandlerInterface;

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
   * @var DateRecurOccurrenceHandlerInterface;
   */
  protected $occurrenceHandler;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['timezone'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Timezone'))
      ->setRequired(FALSE);
    $properties['rrule'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('RRule'))
      ->setRequired(FALSE);
    $properties['infinite'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Is the RRule an infinite rule?'))
      ->setRequired(FALSE);

    $handler = date_recur_create_occurrence_handler($field_definition);
    $properties['occurrences'] = $handler->occurrencePropertyDefinition($field_definition);

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
    $schema['columns']['infinite'] = [
      'description' => 'Infinity of the repeat rule.',
      'type' => 'int',
      'size' => 'tiny',
    ];
    $schema['columns']['timezone'] = [
      'description' => 'The timezone',
      'type' => 'varchar',
      'length' => 255,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);
    return $values;
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
    $handler_options = [];
    /** @var DateRecurOccurrenceHandlerManager $manager */
    $manager = \Drupal::getContainer()
      ->get('plugin.manager.date_recur_occurrence_handler');
    foreach ($manager->getDefinitions() as $id => $definition) {
      $handler_options[$id] = $definition['label'];
    }
    $elements['occurrence_handler_plugin'] = [
      '#type' => 'select',
      '#title' => t('Occurrence handler'),
      '#description' => t('Select an occurrence handler for calculating, saving and retrieving occurrences.'),
      '#options' => $handler_options,
      '#default_value' => $this->getSetting('occurrence_handler_plugin'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'precreate' => 'P2Y',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);
    $options = [];
    for ($i = 1; $i < 6; $i++) {
      $options['P' . $i . 'Y'] = $this->formatPlural($i, '@count year', '@count years', ['@count' => $i]);
    }
    $element['precreate'] = [
      '#type' => 'select',
      '#title' => t('Precreate occurrences'),
      '#description' => t('For infinitely repeating dates, precreate occurrences for this amount of time in the views cache table.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('precreate'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $rrule = $this->get('rrule')->getValue();
    return parent::isEmpty() || ($rrule === NULL || $rrule === '');
  }

  /**
   * Get the date storage format of this field.
   *
   * @return string
   */
  public function getDateStorageFormat() {
    switch ($this->getSetting('daterange_type')) {
      case DateRangeItem::DATETIME_TYPE_DATE:
        return DATETIME_DATE_STORAGE_FORMAT;
        break;
      default:
        return DATETIME_DATETIME_STORAGE_FORMAT;
        break;
    }
  }

  /**
   * Get the occurrence handler and initialize it.
   *
   * @return DateRecurOccurrenceHandlerInterface|bool
   */
  public function getOccurrenceHandler() {
    if (empty($this->occurrenceHandler)) {
      $pluginName = $this->getSetting('occurrence_handler_plugin');
      /** @var DateRecurOccurrenceHandlerManager $manager */
      $manager = \Drupal::getContainer()
        ->get('plugin.manager.date_recur_occurrence_handler');
      /** @var DateRecurOccurrenceHandlerInterface $occurrenceHandler */
      $this->occurrenceHandler = $manager->createInstance($pluginName);
      $this->occurrenceHandler->init($this);
    }
    return $this->occurrenceHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Enforce that the occurrence handler is re-initialized.
    $this->occurrenceHandler = NULL;
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    $this->infinite = $this->getOccurrenceHandler()->isInfinite();
  }

  /**
   * Get the item's delta.
   *
   * Field items have their name property set to the delta.
   *
   * @return int
   */
  public function getDelta() {
    return (int) $this->name;
  }
}
